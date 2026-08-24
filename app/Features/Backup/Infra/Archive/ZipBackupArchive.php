<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Archive;

use App\Features\Backup\App\Contracts\BackupArchiveInterface;
use App\Features\Backup\Domain\ValueObjects\Manifest;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

use function array_keys;
use function date;
use function dirname;
use function ltrim;
use function str_replace;
use function strlen;
use function substr;

/**
 * The encrypted `.zip` archive surface (backup.md:308-338): every entry
 * of the staging directory (the `data/` JSONL files, `assets/` and
 * `manifest.json`) is stored with per-entry AES-256 encryption via
 * `ZipArchive::setEncryptionName(..., ZipArchive::EM_AES_256, ...)`.
 * The encryption key is the raw 44-character base64 value of the active
 * (or imported) `backup_encryption_keys` row.
 *
 * The `encryptionEnabled` flag mirrors `config('backup.encryption_enabled')`
 * (backup.md:503-506): when false, `create()` skips encryption entirely
 * and `validate()`/`readManifest()` accept any key (an unencrypted
 * archive reads with any password). The produced archive follows the
 * B18 filename convention `backup-{Y-m-d-His}-k{id}.zip` — the key id
 * suffix is passed by the caller (the workflow owns the key record's
 * id); the optional `$filename` lets the create workflow force the
 * exact name the pending `backups` row carries, so the record, the
 * RustFS object key and the offline artifact always agree (B302).
 *
 * Failures (unopenable archive, wrong key, corrupt manifest) throw a
 * `RuntimeException` — the DR import's "invalid key or corrupted
 * archive" semantics (backup.md:597).
 */
final class ZipBackupArchive implements BackupArchiveInterface
{
    public function __construct(
        private readonly bool $encryptionEnabled = true,
    ) {
    }

    public function create(string $sourceDir, string $encryptionKey, ?int $encryptionKeyId = null, ?string $filename = null): string
    {
        $filename ??= 'backup-'.date('Y-m-d-His').($encryptionKeyId !== null ? '-k'.$encryptionKeyId : '').'.zip';
        $path = dirname($sourceDir).'/'.$filename;

        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Unable to create the backup archive at '{$path}'.");
        }

        try {
            $entries = $this->fileEntries($sourceDir);

            foreach ($entries as $entryName => $filePath) {
                $zip->addFile($filePath, $entryName);
            }

            if ($this->encryptionEnabled) {
                foreach (array_keys($entries) as $entryName) {
                    $zip->setEncryptionName($entryName, ZipArchive::EM_AES_256, $encryptionKey);
                }
            }
        } finally {
            $zip->close();
        }

        return $path;
    }

    public function validate(string $path, string $encryptionKey): Manifest
    {
        return $this->readManifest($path, $encryptionKey);
    }

    public function extract(string $path, string $encryptionKey, string $destDir): void
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException("The backup archive at '{$path}' cannot be opened.");
        }

        $zip->setPassword($encryptionKey);

        try {
            if (! $zip->extractTo($destDir)) {
                throw new RuntimeException(
                    'The encryption key does not match this backup or the archive is corrupt (extraction failed).',
                );
            }
        } finally {
            $zip->close();
        }
    }

    public function readManifest(string $path, string $encryptionKey): Manifest
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException("The backup archive at '{$path}' cannot be opened.");
        }

        $zip->setPassword($encryptionKey);

        try {
            $manifestJson = $zip->getFromName('manifest.json');
        } finally {
            $zip->close();
        }

        if ($manifestJson === false) {
            throw new RuntimeException(
                'The encryption key does not match this backup or the archive is corrupt (manifest unreadable).',
            );
        }

        return Manifest::fromJson($manifestJson);
    }

    /**
     * Every file under `$sourceDir`, keyed by its archive-relative entry
     * name (forward slashes, e.g. `data/settings.jsonl`).
     *
     * @return array<string, string>
     */
    private function fileEntries(string $sourceDir): array
    {
        $entries = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $filePath = $file->getPathname();
            $entryName = ltrim(str_replace('\\', '/', substr($filePath, strlen($sourceDir))), '/');

            $entries[$entryName] = $filePath;
        }

        return $entries;
    }
}
