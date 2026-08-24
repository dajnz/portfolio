<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * Writes one table to a JSONL export file (backup.md:180-182, 292-296):
 * consumes the source in batches and appends one
 * `json_encode($row, JSON_UNESCAPED_UNICODE)` line per row — the
 * backup creation path. Returns the number of rows written — the
 * manifest's per-table record counts come from the export itself, so
 * the workflow needs no separate DB count. Implemented by
 * `JsonlTableSerializer` in the Infra layer.
 */
interface DbTableExportable
{
    public function writeToFile(DbTableBatchReadable $source, string $path): int;
}
