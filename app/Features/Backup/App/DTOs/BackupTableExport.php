<?php

declare(strict_types=1);

namespace App\Features\Backup\App\DTOs;

use App\Features\Backup\App\Contracts\DbTableBatchReadable;
use App\Features\Backup\App\Contracts\DbTableExportable;

/**
 * One table of the creation pipeline (backup.md:379): its name (the
 * JSONL file + manifest record-count key) and the ready instances the
 * workflow uses — the reader (keyset columns already wired, B305) and
 * the JSONL exporter. Built by the Infra wiring from
 * `config('backup.tables')` + `config('backup.table_keys')`; the app
 * layer never constructs or names the infra classes.
 */
final readonly class BackupTableExport
{
    public function __construct(
        public string $name,
        public DbTableBatchReadable $reader,
        public DbTableExportable $exporter,
    ) {
    }
}
