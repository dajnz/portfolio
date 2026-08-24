<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Backup;

use App\Features\Backup\Domain\ValueObjects\Manifest;
use App\Features\Backup\Domain\ValueObjects\ManifestAsset;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The backup archive manifest (backup.md:83-128): `fromJson()`/
 * `fromArray()` parsing of the stored `manifest.json`, the
 * `assets_count`/`assets_size_bytes` consistency invariants and the
 * pretty-printed `toJson()` round-trip the creation workflow writes.
 */
describe('Manifest', function (): void {
    it('exposes the parsed fields', function (): void {
        $manifest = new Manifest(
            createdAt: new DateTimeImmutable('2026-07-09T02:00:00Z'),
            version: Manifest::VERSION,
            records: ['about_info' => 1, 'settings' => 14],
            assetsCount: 2,
            assetsSizeBytes: 512000,
            assets: [
                new ManifestAsset('projects/abc123/preview.jpg', 245760),
                new ManifestAsset('blog/def456/lead.png', 266240),
            ],
        );

        expect($manifest->toCreatedAt())->toEqual(new DateTimeImmutable('2026-07-09T02:00:00Z'))
            ->and($manifest->toVersion())->toBe(Manifest::VERSION)
            ->and($manifest->toRecordCounts())->toBe(['about_info' => 1, 'settings' => 14])
            ->and($manifest->recordCount('about_info'))->toBe(1)
            ->and($manifest->recordCount('missing_table'))->toBe(0)
            ->and($manifest->toAssetsCount())->toBe(2)
            ->and($manifest->toAssetsSizeBytes())->toBe(512000)
            ->and($manifest->assetPaths())->toBe(['projects/abc123/preview.jpg', 'blog/def456/lead.png'])
            ->and($manifest->toAssets())->toHaveCount(2)
            ->and($manifest->toAssets()[0]->toPath())->toBe('projects/abc123/preview.jpg')
            ->and($manifest->toAssets()[0]->toSizeBytes())->toBe(245760);
    });

    it('parses a stored manifest.json', function (): void {
        $manifest = Manifest::fromJson(
            '{"created_at":"2026-07-09T02:00:00Z","version":"1.0",'
            .'"records":{"about_info":1,"settings":14},'
            .'"assets_count":2,"assets_size_bytes":512000,'
            .'"assets":[{"path":"projects/abc123/preview.jpg","size_bytes":245760},'
            .'{"path":"blog/def456/lead.png","size_bytes":266240}]}',
        );

        expect($manifest->toCreatedAt())->toEqual(new DateTimeImmutable('2026-07-09T02:00:00Z'))
            ->and($manifest->toVersion())->toBe('1.0')
            ->and($manifest->toRecordCounts())->toBe(['about_info' => 1, 'settings' => 14])
            ->and($manifest->toAssetsCount())->toBe(2)
            ->and($manifest->toAssetsSizeBytes())->toBe(512000);
    });

    it('round-trips through the pretty-printed toJson shape', function (): void {
        $manifest = new Manifest(
            createdAt: new DateTimeImmutable('2026-07-09T02:00:00Z'),
            version: Manifest::VERSION,
            records: ['about_info' => 1, 'settings' => 14],
            assetsCount: 2,
            assetsSizeBytes: 512000,
            assets: [
                new ManifestAsset('projects/abc123/preview.jpg', 245760),
                new ManifestAsset('blog/def456/lead.png', 266240),
            ],
        );

        $reparsed = Manifest::fromJson($manifest->toJson());

        expect($manifest->toJson())->toBe(
            "{\n"
            .'    "created_at": "2026-07-09T02:00:00Z",'."\n"
            .'    "version": "1.0",'."\n"
            .'    "records": {'."\n"
            .'        "about_info": 1,'."\n"
            .'        "settings": 14'."\n"
            .'    },'."\n"
            .'    "assets_count": 2,'."\n"
            .'    "assets_size_bytes": 512000,'."\n"
            .'    "assets": ['."\n"
            .'        {'."\n"
            .'            "path": "projects/abc123/preview.jpg",'."\n"
            .'            "size_bytes": 245760'."\n"
            .'        },'."\n"
            .'        {'."\n"
            .'            "path": "blog/def456/lead.png",'."\n"
            .'            "size_bytes": 266240'."\n"
            .'        }'."\n"
            .'    ]'."\n"
            .'}',
        );

        expect($reparsed->toCreatedAt())->toEqual($manifest->toCreatedAt())
            ->and($reparsed->toRecordCounts())->toBe($manifest->toRecordCounts())
            ->and($reparsed->assetPaths())->toBe($manifest->assetPaths());
    });

    it('rejects malformed JSON and non-object payloads', function (): void {
        expect(fn () => Manifest::fromJson('{not json'))->toThrow(
            InvalidArgumentException::class,
            'Manifest is not valid JSON',
        );

        expect(fn () => Manifest::fromJson('[1, 2]'))->toThrow(
            InvalidArgumentException::class,
            'Manifest JSON must decode to an object.',
        );
    });

    it('requires every documented field', function (): void {
        expect(fn () => Manifest::fromArray([
            'created_at' => '2026-07-09T02:00:00Z',
            'version' => Manifest::VERSION,
            'records' => [],
            'assets_count' => 0,
            'assets_size_bytes' => 0,
        ]))->toThrow(InvalidArgumentException::class, "Manifest is missing the 'assets' field.");
    });

    it('rejects an invalid created_at', function (): void {
        expect(fn () => Manifest::fromArray([
            'created_at' => 'not-a-date',
            'version' => Manifest::VERSION,
            'records' => [],
            'assets_count' => 0,
            'assets_size_bytes' => 0,
            'assets' => [],
        ]))->toThrow(InvalidArgumentException::class, 'Manifest created_at must be a valid date-time.');
    });

    it('rejects an empty version and malformed record counts', function (): void {
        expect(fn () => new Manifest(
            createdAt: new DateTimeImmutable('2026-07-09T02:00:00Z'),
            version: '',
            records: [],
            assetsCount: 0,
            assetsSizeBytes: 0,
            assets: [],
        ))->toThrow(InvalidArgumentException::class, 'Manifest version must not be empty.');

        expect(fn () => new Manifest(
            createdAt: new DateTimeImmutable('2026-07-09T02:00:00Z'),
            version: Manifest::VERSION,
            records: ['about_info' => -1],
            assetsCount: 0,
            assetsSizeBytes: 0,
            assets: [],
        ))->toThrow(InvalidArgumentException::class, 'Manifest record counts must be non-negative integers per table.');
    });

    it('enforces the assets consistency invariants', function (): void {
        expect(fn () => new Manifest(
            createdAt: new DateTimeImmutable('2026-07-09T02:00:00Z'),
            version: Manifest::VERSION,
            records: [],
            assetsCount: 3,
            assetsSizeBytes: 245760,
            assets: [new ManifestAsset('projects/abc123/preview.jpg', 245760)],
        ))->toThrow(InvalidArgumentException::class, 'Manifest assets_count must match the asset list length.');

        expect(fn () => new Manifest(
            createdAt: new DateTimeImmutable('2026-07-09T02:00:00Z'),
            version: Manifest::VERSION,
            records: [],
            assetsCount: 1,
            assetsSizeBytes: 245761,
            assets: [new ManifestAsset('projects/abc123/preview.jpg', 245760)],
        ))->toThrow(InvalidArgumentException::class, 'Manifest assets_size_bytes must equal the sum of the asset sizes.');
    });

    it('rejects malformed asset entries', function (): void {
        expect(fn () => Manifest::fromArray([
            'created_at' => '2026-07-09T02:00:00Z',
            'version' => Manifest::VERSION,
            'records' => [],
            'assets_count' => 1,
            'assets_size_bytes' => 0,
            'assets' => [['path' => 'projects/abc123/preview.jpg']],
        ]))->toThrow(InvalidArgumentException::class, 'Manifest asset entries must carry a path and a size_bytes.');

        expect(fn () => Manifest::fromArray([
            'created_at' => '2026-07-09T02:00:00Z',
            'version' => Manifest::VERSION,
            'records' => [],
            'assets_count' => 2.5,
            'assets_size_bytes' => 0,
            'assets' => [],
        ]))->toThrow(InvalidArgumentException::class, 'Manifest assets_count and assets_size_bytes must be integers.');
    });

    it('validates the asset path and size', function (): void {
        expect(fn () => new ManifestAsset('', 10))->toThrow(
            InvalidArgumentException::class,
            'Manifest asset path must not be empty.',
        );

        expect(fn () => new ManifestAsset('projects/abc123/preview.jpg', -1))->toThrow(
            InvalidArgumentException::class,
            'Manifest asset size must not be negative.',
        );
    });
});
