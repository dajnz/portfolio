<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * Writes one table to a JSONL export file (backup.md:180-182, 292-296):
 * consumes the source in batches and appends one
 * `json_encode($row, JSON_UNESCAPED_UNICODE)` line per row — the
 * backup creation path. Implemented by `JsonlTableSerializer` in the
 * Infra layer.
 */
interface DbTableExportable
{
    public function writeToFile(DbTableBatchReadable $source, string $path): void;
}
