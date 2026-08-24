<?php

declare(strict_types=1);

namespace App\Features\Backup\App\DTOs;

use App\Features\Backup\App\Contracts\DbTableBatchWritable;
use App\Features\Backup\App\Contracts\DbTableImportable;

/**
 * One table of the restore pipeline (backup.md:402): its name (the
 * JSONL file key) and the ready instances the workflow uses — the
 * JSONL importer and the batch writer (the `settings` variant already
 * upserts by key). Built by the Infra wiring from
 * `config('backup.tables')`; the app layer never constructs or names
 * the infra classes.
 */
final readonly class BackupTableImport
{
    public function __construct(
        public string $name,
        public DbTableImportable $importer,
        public DbTableBatchWritable $writer,
    ) {
    }
}
