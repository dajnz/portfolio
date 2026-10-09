<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Services;

use App\Features\Backup\App\DTOs\BackupTableExport;
use App\Features\Backup\App\DTOs\BackupTableImport;
use App\Features\Backup\Infra\Serialization\JsonlTableSerializer;
use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchReader;
use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchWriter;

use function config;

/**
 * Builds the per-table workflow bundles (ADR-0018) from
 * `config('backup.tables')` + `config('backup.table_keys')`: the
 * creation pipeline's readers (keyset columns wired per table, ADR-0018)
 * + JSONL exporters, and the restore pipeline's importers + batch
 * writers (the `settings` variant upserts by key, ADR-0018). The app
 * layer receives the ready instances — it never constructs or names
 * the infra classes.
 */
final class BackupTableFactory
{
    /**
     * @return list<BackupTableExport>
     */
    public function exportTables(): array
    {
        $tables = [];

        foreach (config('backup.tables', []) as $table) {
            $tables[] = new BackupTableExport(
                name: $table,
                reader: new LaravelDbTableBatchReader($table, config('backup.table_keys')[$table] ?? 'id'),
                exporter: new JsonlTableSerializer($table),
            );
        }

        return $tables;
    }

    /**
     * @return list<BackupTableImport>
     */
    public function importTables(): array
    {
        $tables = [];

        foreach (config('backup.tables', []) as $table) {
            $tables[] = new BackupTableImport(
                name: $table,
                importer: new JsonlTableSerializer($table),
                writer: new LaravelDbTableBatchWriter($table, $table === 'settings' ? 'key' : null),
            );
        }

        return $tables;
    }
}
