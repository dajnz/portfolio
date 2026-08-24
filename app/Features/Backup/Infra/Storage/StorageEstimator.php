<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Storage;

use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;
use App\Features\Backup\App\Contracts\DbTableBatchReadable;
use App\Features\Backup\App\Contracts\StorageEstimatorInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;

use function disk_free_space;
use function is_dir;
use function json_encode;
use function strlen;

use const JSON_UNESCAPED_UNICODE;

/**
 * The backup pre-flight estimator (backup.md:242-249, 378): the
 * required staging space is `2 × (estimated data + assets)` — data is
 * the summed serialized JSONL size of the rows the given batch reader
 * yields (what the archive will actually contain), assets the summed
 * size of the bucket's objects (enumerated via the collector, sized via
 * the assets disk), available space the free space of the staging disk.
 * `hasEnoughSpace()` returns false when the available space is below
 * the 2× requirement (backup.md:249).
 */
final class StorageEstimator implements StorageEstimatorInterface
{
    /**
     * Rows per batch while streaming the data-size estimate.
     */
    private const READ_BATCH_SIZE = 500;

    public function __construct(
        private readonly AssetPathCollectorInterface $assetCollector,
        private readonly Filesystem $assetsDisk,
        private readonly string $stagingPath,
    ) {
    }

    /**
     * @param  string[]  $tableNames
     */
    public function estimateDataSize(array $tableNames, DbTableBatchReadable $batchReader): int
    {
        if ($tableNames === []) {
            throw new InvalidArgumentException('estimateDataSize requires at least one table name.');
        }

        $bytes = 0;

        foreach ($batchReader->readBatch(self::READ_BATCH_SIZE) as $row) {
            $bytes += strlen((string) json_encode($row, JSON_UNESCAPED_UNICODE)) + 1;
        }

        return $bytes;
    }

    public function getAssetSize(string $bucket): int
    {
        $bytes = 0;

        foreach ($this->assetCollector->listPaths($bucket) as $path) {
            $bytes += (int) $this->assetsDisk->size($path);
        }

        return $bytes;
    }

    /**
     * The free space of the staging disk in bytes — 0 when the path is
     * missing or unreadable (a conservative pre-flight abort).
     */
    public function getAvailableSpace(): int
    {
        if (! is_dir($this->stagingPath)) {
            return 0;
        }

        $free = disk_free_space($this->stagingPath);

        return $free === false ? 0 : (int) $free;
    }

    /**
     * @param  string[]  $tableNames
     */
    public function hasEnoughSpace(array $tableNames, string $bucket, DbTableBatchReadable $batchReader): bool
    {
        $required = 2 * ($this->estimateDataSize($tableNames, $batchReader) + $this->getAssetSize($bucket));

        return $this->getAvailableSpace() >= $required;
    }
}
