<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Domain\ValueObjects\Manifest;
use App\Features\Backup\Domain\ValueObjects\ManifestAsset;
use App\Features\Backup\Infra\Archive\ZipBackupArchive;
use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

use function file_get_contents;
use function file_put_contents;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * The encrypted `.zip` archive (backup.md:308-338): AES-256 per-entry
 * round-trip (create → validate → readManifest → extract), the
 * wrong-key rejection (backup.md:192-195) and the encryption-disabled
 * path (backup.md:503-506) where any key is accepted.
 */

/**
 * Builds a staging dir with `manifest.json`, a `data/` JSONL file and
 * an `assets/` file.
 *
 * @return array{0: string, 1: Manifest} [dir, manifest]
 */
function backupArchiveStagingDir(): array
{
    $dir = sys_get_temp_dir().'/backup-fixture-'.uniqid();
    mkdir("{$dir}/data", 0777, true);
    mkdir("{$dir}/assets", 0777, true);

    file_put_contents("{$dir}/data/settings.jsonl", "{\"key\":\"site_title\",\"value\":\"Portfolio\"}\n");
    file_put_contents("{$dir}/assets/lead.png", 'png-bytes');

    $manifest = new Manifest(
        createdAt: new DateTimeImmutable('2026-07-09T02:00:00Z'),
        version: Manifest::VERSION,
        records: ['settings' => 1],
        assetsCount: 1,
        assetsSizeBytes: 9,
        assets: [new ManifestAsset('assets/lead.png', 9)],
    );

    file_put_contents("{$dir}/manifest.json", $manifest->toJson());

    return [$dir, $manifest];
}

/**
 * Builds the staging dir and archives it.
 *
 * @return array{0: string, 1: string, 2: Manifest} [dir, zipPath, manifest]
 */
function backupArchiveFixture(string $key = 'test-key', ?int $keyId = null, bool $encryptionEnabled = true): array
{
    [$dir, $manifest] = backupArchiveStagingDir();

    $zipPath = (new ZipBackupArchive($encryptionEnabled))->create($dir, $key, $keyId);

    return [$dir, $zipPath, $manifest];
}

function backupArchiveCleanup(string $dir, string $zipPath): void
{
    foreach ([$zipPath, "{$dir}/data/settings.jsonl", "{$dir}/assets/lead.png", "{$dir}/manifest.json"] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    foreach (["{$dir}/data", "{$dir}/assets", $dir] as $directory) {
        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }
}

function backupExtractCleanup(string $destDir): void
{
    foreach (["{$destDir}/data/settings.jsonl", "{$destDir}/assets/lead.png", "{$destDir}/manifest.json"] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }

    foreach (["{$destDir}/data", "{$destDir}/assets", $destDir] as $dir) {
        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }
}

describe('ZipBackupArchive', function (): void {
    it('creates an AES-256 encrypted archive with the ADR-0018 filename', function (): void {
        [$dir, $zipPath] = backupArchiveFixture(keyId: 3);

        try {
            expect(is_file($zipPath))->toBeTrue()
                ->and($zipPath)->toMatch('/backup-\d{4}-\d{2}-\d{2}-\d{6}-k3\.zip$/');

            $zip = new ZipArchive();
            $zip->open($zipPath);
            $zip->setPassword('test-key');
            $encrypted = $zip->getFromName('data/settings.jsonl');
            $zip->close();

            expect($encrypted)->toBe("{\"key\":\"site_title\",\"value\":\"Portfolio\"}\n");
        } finally {
            backupArchiveCleanup($dir, $zipPath);
        }
    });

    it('validates the archive with the correct key', function (): void {
        [$dir, $zipPath, $manifest] = backupArchiveFixture(keyId: 3);

        try {
            $validated = (new ZipBackupArchive())->validate($zipPath, 'test-key');

            expect($validated->toVersion())->toBe(Manifest::VERSION)
                ->and($validated->toRecordCounts())->toBe(['settings' => 1])
                ->and($validated->assetPaths())->toBe(['assets/lead.png'])
                ->and($validated->toCreatedAt())->toEqual($manifest->toCreatedAt());
        } finally {
            backupArchiveCleanup($dir, $zipPath);
        }
    });

    it('reads the manifest from the archive', function (): void {
        [$dir, $zipPath, $manifest] = backupArchiveFixture(keyId: 3);

        try {
            $manifestBack = (new ZipBackupArchive())->readManifest($zipPath, 'test-key');

            expect($manifestBack->toVersion())->toBe($manifest->toVersion())
                ->and($manifestBack->toRecordCounts())->toBe($manifest->toRecordCounts())
                ->and($manifestBack->assetPaths())->toBe($manifest->assetPaths());
        } finally {
            backupArchiveCleanup($dir, $zipPath);
        }
    });

    it('extracts the archive entries into the destination directory', function (): void {
        [$dir, $zipPath] = backupArchiveFixture(keyId: 3);
        $destDir = sys_get_temp_dir().'/backup-extract-'.uniqid();
        mkdir($destDir);

        try {
            (new ZipBackupArchive())->extract($zipPath, 'test-key', $destDir);

            expect(is_file("{$destDir}/manifest.json"))->toBeTrue()
                ->and(file_get_contents("{$destDir}/data/settings.jsonl"))
                ->toBe("{\"key\":\"site_title\",\"value\":\"Portfolio\"}\n")
                ->and(file_get_contents("{$destDir}/assets/lead.png"))->toBe('png-bytes');
        } finally {
            backupExtractCleanup($destDir);
            backupArchiveCleanup($dir, $zipPath);
        }
    });

    it('rejects a wrong encryption key on validate', function (): void {
        [$dir, $zipPath] = backupArchiveFixture(keyId: 3);

        try {
            expect(fn () => (new ZipBackupArchive())->validate($zipPath, 'wrong-key'))->toThrow(
                RuntimeException::class,
                'The encryption key does not match this backup or the archive is corrupt',
            );
        } finally {
            backupArchiveCleanup($dir, $zipPath);
        }
    });

    it('rejects a wrong encryption key on extract', function (): void {
        [$dir, $zipPath] = backupArchiveFixture(keyId: 3);
        $destDir = sys_get_temp_dir().'/backup-extract-'.uniqid();
        mkdir($destDir);

        try {
            expect(fn () => (new ZipBackupArchive())->extract($zipPath, 'wrong-key', $destDir))->toThrow(
                RuntimeException::class,
                'The encryption key does not match this backup or the archive is corrupt',
            );
        } finally {
            backupExtractCleanup($destDir);
            backupArchiveCleanup($dir, $zipPath);
        }
    });

    it('skips encryption when disabled and accepts any key', function (): void {
        [$dir, $zipPath] = backupArchiveFixture(encryptionEnabled: false);

        try {
            expect($zipPath)->toMatch('/backup-\d{4}-\d{2}-\d{2}-\d{6}\.zip$/');

            $manifest = (new ZipBackupArchive(false))->validate($zipPath, 'any-key');
            expect($manifest->toRecordCounts())->toBe(['settings' => 1]);

            $manifestBack = (new ZipBackupArchive(false))->readManifest($zipPath, 'also-any-key');
            expect($manifestBack->toVersion())->toBe(Manifest::VERSION);
        } finally {
            backupArchiveCleanup($dir, $zipPath);
        }
    });

    it('rejects an unopenable archive', function (): void {
        $junk = sys_get_temp_dir().'/backup-junk-'.uniqid().'.zip';
        file_put_contents($junk, 'not-a-zip');

        try {
            expect(fn () => (new ZipBackupArchive())->validate($junk, 'test-key'))
                ->toThrow(RuntimeException::class);
        } finally {
            @unlink($junk);
        }
    });

    it('rejects an archive without a manifest', function (): void {
        [$dir] = backupArchiveStagingDir();
        @unlink("{$dir}/manifest.json");

        $zipPath = (new ZipBackupArchive())->create($dir, 'test-key', 3);

        try {
            expect(fn () => (new ZipBackupArchive())->validate($zipPath, 'test-key'))->toThrow(
                RuntimeException::class,
                'The encryption key does not match this backup or the archive is corrupt',
            );
        } finally {
            backupArchiveCleanup($dir, $zipPath);
        }
    });
});
