<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Services;

use App\Features\Backup\App\DTOs\BackupTableExport;
use App\Features\Backup\App\DTOs\BackupTableImport;
use App\Features\Backup\Infra\Serialization\JsonlTableSerializer;
use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchReader;
use App\Features\Backup\Infra\Serialization\LaravelDbTableBatchWriter;
use Illuminate\Support\Facades\Config;

/**
 * Builds the per-table workflow bundles from `config('backup.tables')` +
 * `config('backup.table_keys')`: the creation pipeline's readers (keyset
 * columns wired per table) + JSONL exporters, and the restore pipeline's
 * importers + batch writers (the `settings` variant upserts by key). The
 * app layer receives the ready instances — it never constructs or names
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

        foreach (Config::get('backup.tables', []) as $table) {
            $tables[] = new BackupTableExport(
                name: $table,
                reader: new LaravelDbTableBatchReader($table, Config::get('backup.table_keys')[$table] ?? 'id'),
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

        foreach (Config::get('backup.tables', []) as $table) {
            $tables[] = new BackupTableImport(
                name: $table,
                importer: new JsonlTableSerializer($table),
                writer: new LaravelDbTableBatchWriter($table, $table === 'settings' ? 'key' : null),
            );
        }

        return $tables;
    }
}
