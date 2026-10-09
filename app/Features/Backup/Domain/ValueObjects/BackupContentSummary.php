<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\ValueObjects;

use InvalidArgumentException;

use function count;
use function is_int;
use function is_string;

/**
 * The archive content stamp of a `backups` row: the byte size of the
 * archive and the manifest summary — the per-table record counts, the
 * asset count and the assets byte size.
 *
 * The fields are nullable to model the pending row (none set yet); the
 * all-or-nothing invariant keeps a summary either fully empty or fully
 * populated — the values are stamped atomically by `Backup::complete()`.
 * The record counts must be non-negative integers per table, every
 * present number must be ≥ 0.
 */
final class BackupContentSummary
{
    /**
     * @param  array<string, int>|null  $recordCounts  Table name => row count at backup time.
     */
    public function __construct(
        private readonly ?int $sizeBytes,
        private readonly ?int $assetsCount,
        private readonly ?int $assetsSizeBytes,
        private readonly ?array $recordCounts,
    ) {
        $this->validateSizeBytes($sizeBytes);
        $this->validateAssetsCount($assetsCount);
        $this->validateAssetsSizeBytes($assetsSizeBytes);
        $this->validateRecordCounts($recordCounts);
        $this->validateAllOrNothing($sizeBytes, $assetsCount, $assetsSizeBytes, $recordCounts);
    }

    /**
     * Whether every field is set — the shape of a completed backup.
     */
    public function isComplete(): bool
    {
        return $this->sizeBytes !== null
            && $this->assetsCount !== null
            && $this->assetsSizeBytes !== null
            && $this->recordCounts !== null;
    }

    public function toSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function toAssetsCount(): ?int
    {
        return $this->assetsCount;
    }

    public function toAssetsSizeBytes(): ?int
    {
        return $this->assetsSizeBytes;
    }

    /**
     * @return array<string, int>|null
     */
    public function toRecordCounts(): ?array
    {
        return $this->recordCounts;
    }

    private function validateSizeBytes(?int $sizeBytes): void
    {
        if ($sizeBytes !== null && $sizeBytes < 0) {
            throw new InvalidArgumentException('Backup size must not be negative.');
        }
    }

    private function validateAssetsCount(?int $assetsCount): void
    {
        if ($assetsCount !== null && $assetsCount < 0) {
            throw new InvalidArgumentException('Backup assets count must not be negative.');
        }
    }

    private function validateAssetsSizeBytes(?int $assetsSizeBytes): void
    {
        if ($assetsSizeBytes !== null && $assetsSizeBytes < 0) {
            throw new InvalidArgumentException('Backup assets size must not be negative.');
        }
    }

    /**
     * @param  array<string, int>|null  $recordCounts
     */
    private function validateRecordCounts(?array $recordCounts): void
    {
        if ($recordCounts === null) {
            return;
        }

        foreach ($recordCounts as $table => $count) {
            if (! is_string($table) || $table === '' || ! is_int($count) || $count < 0) {
                throw new InvalidArgumentException('Backup record counts must be non-negative integers per table.');
            }
        }
    }

    /**
     * @param  array<string, int>|null  $recordCounts
     */
    private function validateAllOrNothing(
        ?int $sizeBytes,
        ?int $assetsCount,
        ?int $assetsSizeBytes,
        ?array $recordCounts,
    ): void {
        $set = [$sizeBytes, $assetsCount, $assetsSizeBytes, $recordCounts];
        $present = array_filter($set, static fn (mixed $value): bool => $value !== null);

        if ($present !== [] && count($present) !== count($set)) {
            throw new InvalidArgumentException('Backup content summary must be either empty or fully populated.');
        }
    }
}
