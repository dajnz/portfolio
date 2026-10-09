<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Services;

use App\Features\Backup\App\Contracts\TableTruncatorInterface;
use App\Features\Backup\App\DTOs\BackupTableImport;
use RuntimeException;

use function file_exists;
use function sprintf;

/**
 * The restore's data plane: truncates every included table child-first
 * (incl. the analytics wipe; `feature_flags` is never in the order) and
 * imports each table's JSONL file back with the original IDs (the
 * `settings` writer already upserts by key). Split off the restore
 * workflow so the transport/orchestration steps (archive, assets,
 * maintenance) stay in `RestoreWorkflowService`.
 */
final class DatabaseRestoreService
{
    /**
     * @param  list<string>  $truncateOrder
     * @param  list<BackupTableImport>  $tables
     */
    public function __construct(
        private readonly TableTruncatorInterface $truncator,
        private readonly array $truncateOrder,
        private readonly array $tables,
    ) {
    }

    public function restore(string $extractDir): void
    {
        $this->truncator->truncateAll($this->truncateOrder);
        $this->importTables($extractDir);
    }

    /**
     * Step 6: import every table's JSONL file back — original IDs
     * preserved, the `settings` table upserts by key. A table file
     * absent from the archive (a backup created before the table
     * existed) restores as empty.
     */
    private function importTables(string $extractDir): void
    {
        foreach ($this->tables as $table) {
            $path = sprintf('%s/data/%s.jsonl', $extractDir, $table->name);

            if (! file_exists($path)) {
                continue;
            }

            try {
                $table->importer->readFromFile($path, $table->writer);
            } catch (\Throwable $exception) {
                throw new RuntimeException(
                    sprintf('Unable to restore the "%s" table from its JSONL file.', $table->name),
                    previous: $exception,
                );
            }
        }
    }
}
