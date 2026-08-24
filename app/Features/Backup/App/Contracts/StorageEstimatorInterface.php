<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * The backup pre-flight estimator surface (backup.md:242-249): the
 * 2× space rule. An abstraction over the infra `StorageEstimator`
 * (Backup/Infra/Storage) so the app-layer workflows never name the
 * concrete estimator.
 */
interface StorageEstimatorInterface
{
    /**
     * @param  string[]  $tableNames
     */
    public function estimateDataSize(array $tableNames, DbTableBatchReadable $batchReader): int;

    /**
     * The byte size of the bucket's objects (enumerated via the asset
     * collector, sized via the assets disk).
     */
    public function getAssetSize(string $bucket): int;

    /**
     * The free space of the staging disk in bytes — 0 when the path is
     * missing or unreadable (a conservative pre-flight abort).
     */
    public function getAvailableSpace(): int;

    /**
     * @param  string[]  $tableNames
     */
    public function hasEnoughSpace(array $tableNames, string $bucket, DbTableBatchReadable $batchReader): bool;
}
