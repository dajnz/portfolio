<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

use function count;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error_msg;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The backup archive manifest (backup.md:83-128): the archive metadata
 * written as `manifest.json` at creation time and parsed back at
 * validate/restore. Carries the creation timestamp, the format
 * version, the per-table record counts and the full asset listing —
 * the restore workflow's exact-mirror source (objects present in the
 * live bucket but absent from `assets` are deleted).
 *
 * The constructor protects the invariants: every table count is a
 * non-negative integer, and the redundant `assets_count` /
 * `assets_size_bytes` fields must agree with the asset list.
 * `fromJson()`/`fromArray()` parse a stored manifest (single
 * pretty-printed JSON object, backup.md:80); `toJson()` produces the
 * same shape for the creation workflow.
 */
final class Manifest
{
    public const VERSION = '1.0';

    /**
     * @param  array<string, int>  $records  Table name => row count at backup time.
     * @param  ManifestAsset[]  $assets
     */
    public function __construct(
        private readonly DateTimeImmutable $createdAt,
        private readonly string $version,
        private readonly array $records,
        private readonly int $assetsCount,
        private readonly int $assetsSizeBytes,
        private readonly array $assets,
    ) {
        if ($version === '') {
            throw new InvalidArgumentException('Manifest version must not be empty.');
        }

        $this->validateRecords($records);
        $this->validateAssets($assets, $assetsCount, $assetsSizeBytes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['created_at', 'version', 'records', 'assets_count', 'assets_size_bytes', 'assets'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Manifest is missing the '{$key}' field.");
            }
        }

        try {
            $createdAt = new DateTimeImmutable($data['created_at']);
        } catch (\Exception) {
            throw new InvalidArgumentException('Manifest created_at must be a valid date-time.');
        }

        if (! is_string($data['version']) || ! is_array($data['records']) || ! is_array($data['assets'])) {
            throw new InvalidArgumentException('Manifest version, records and assets must have the documented types.');
        }

        if (! is_int($data['assets_count']) || ! is_int($data['assets_size_bytes'])) {
            throw new InvalidArgumentException('Manifest assets_count and assets_size_bytes must be integers.');
        }

        $assets = [];

        foreach ($data['assets'] as $asset) {
            if (! is_array($asset) || ! isset($asset['path'], $asset['size_bytes'])) {
                throw new InvalidArgumentException('Manifest asset entries must carry a path and a size_bytes.');
            }

            if (! is_string($asset['path']) || ! is_int($asset['size_bytes'])) {
                throw new InvalidArgumentException('Manifest asset paths and sizes must have the documented types.');
            }

            $assets[] = new ManifestAsset($asset['path'], $asset['size_bytes']);
        }

        return new self(
            createdAt: $createdAt,
            version: $data['version'],
            records: $data['records'],
            assetsCount: $data['assets_count'],
            assetsSizeBytes: $data['assets_size_bytes'],
            assets: $assets,
        );
    }

    /**
     * Parses a stored `manifest.json` string (backup.md:83-128).
     */
    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Manifest is not valid JSON: '.json_last_error_msg().'.');
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('Manifest JSON must decode to an object.');
        }

        return self::fromArray($data);
    }

    public function toCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toVersion(): string
    {
        return $this->version;
    }

    /**
     * @return array<string, int>
     */
    public function toRecordCounts(): array
    {
        return $this->records;
    }

    /**
     * The row count of the table at backup time — 0 when the table is
     * absent from the manifest (e.g. `settings` before the seeder ran).
     */
    public function recordCount(string $table): int
    {
        return $this->records[$table] ?? 0;
    }

    public function toAssetsCount(): int
    {
        return $this->assetsCount;
    }

    public function toAssetsSizeBytes(): int
    {
        return $this->assetsSizeBytes;
    }

    /**
     * @return ManifestAsset[]
     */
    public function toAssets(): array
    {
        return $this->assets;
    }

    /**
     * @return string[] The asset paths — the restore mirror's source.
     */
    public function assetPaths(): array
    {
        return array_map(
            static fn (ManifestAsset $asset): string => $asset->toPath(),
            $this->assets,
        );
    }

    /**
     * @return array<string, mixed> The wire shape of a stored manifest.
     */
    public function toArray(): array
    {
        return [
            'created_at' => $this->createdAt->format('Y-m-d\TH:i:s\Z'),
            'version' => $this->version,
            'records' => $this->records,
            'assets_count' => $this->assetsCount,
            'assets_size_bytes' => $this->assetsSizeBytes,
            'assets' => array_map(
                static fn (ManifestAsset $asset): array => [
                    'path' => $asset->toPath(),
                    'size_bytes' => $asset->toSizeBytes(),
                ],
                $this->assets,
            ),
        ];
    }

    /**
     * The pretty-printed manifest file content (backup.md:80).
     */
    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  array<string, int>  $records
     */
    private function validateRecords(array $records): void
    {
        foreach ($records as $table => $count) {
            if ($table === '' || ! is_int($count) || $count < 0) {
                throw new InvalidArgumentException('Manifest record counts must be non-negative integers per table.');
            }
        }
    }

    /**
     * @param  ManifestAsset[]  $assets
     */
    private function validateAssets(array $assets, int $assetsCount, int $assetsSizeBytes): void
    {
        if ($assetsCount !== count($assets)) {
            throw new InvalidArgumentException('Manifest assets_count must match the asset list length.');
        }

        $sum = 0;

        foreach ($assets as $asset) {
            if (! $asset instanceof ManifestAsset) {
                throw new InvalidArgumentException('Manifest assets must be ManifestAsset values.');
            }

            $sum += $asset->toSizeBytes();
        }

        if ($assetsSizeBytes !== $sum) {
            throw new InvalidArgumentException('Manifest assets_size_bytes must equal the sum of the asset sizes.');
        }
    }
}
