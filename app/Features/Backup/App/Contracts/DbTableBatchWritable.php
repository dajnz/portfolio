<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * Batch writes into one DB table (backup.md:172-174): accumulates the
 * given rows and INSERTs them in chunks — the JSONL import destination.
 * Implemented by `LaravelDbTableBatchWriter` in the Infra layer; the
 * `settings` variant upserts by `key`.
 */
interface DbTableBatchWritable
{
    /**
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function writeBatch(iterable $rows): void;
}
