<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * Reads one table's JSONL export file back into the DB (backup.md:177-179,
 * 285-303): parses `$path` line by line (`fgets`, one JSON object per
 * line) and feeds the accumulated batches into the writer — the
 * restore import path. Implemented by `JsonlTableSerializer` in the
 * Infra layer.
 */
interface DbTableImportable
{
    public function readFromFile(string $path, DbTableBatchWritable $dest): void;
}
