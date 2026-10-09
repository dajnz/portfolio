<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

use Generator;

/**
 * Batch reads of one DB table: yields every row as an array keyed by
 * column name, in batches of `$batchSize` — the JSONL export source.
 * Implemented by `LaravelDbTableBatchReader` (query builder) in the
 * Infra layer.
 */
interface DbTableBatchReadable
{
    /**
     * @return Generator<array<string, mixed>>
     */
    public function readBatch(int $batchSize): Generator;
}
