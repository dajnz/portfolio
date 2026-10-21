<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Commands;

use App\Features\Shared\App\UseCases\CleanupOrphanedFilesUseCase;
use Illuminate\Console\Command;

/**
 * `app:cleanup-orphaned-files` — deletes RustFS objects in the assets
 * bucket that no registered reference provider claims. Supports
 * `--dry-run` to list orphans without deleting. Registered on a monthly
 * schedule in routes/console.php; the temp backup directory is never
 * scanned because only the assets disk is inspected.
 */
final class CleanupOrphanedFilesCommand extends Command
{
    protected $signature = 'app:cleanup-orphaned-files
        {--dry-run : List orphaned files without deleting them}';

    protected $description = 'Delete RustFS objects not referenced by any DB column or Markdown content';

    public function handle(CleanupOrphanedFilesUseCase $cleanup): int
    {
        $result = $cleanup->cleanup((bool) $this->option('dry-run'));

        if ($result->orphanPaths === []) {
            $this->info('No orphaned files found.');

            return self::SUCCESS;
        }

        foreach ($result->orphanPaths as $path) {
            $this->line(($result->dryRun ? 'Would delete: ' : 'Deleted: ').$path);
        }

        $this->info(($result->dryRun ? 'Orphans found: ' : 'Deleted: ').count($result->orphanPaths));

        return self::SUCCESS;
    }
}
