<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Commands;

use App\Features\Backup\App\UseCases\PruneBackupsUseCase;
use Illuminate\Console\Command;

/**
 * `backup:prune` (backup.md:462): the standalone retention cleanup —
 * removes the oldest backups beyond `backup_max_count` (the DB
 * setting wins, `config('backup.max_count')` is the fallback). Also
 * called automatically after every successful backup
 * (`BackupWorkflowService` step 10).
 */
final class PruneBackupsCommand extends Command
{
    protected $signature = 'backup:prune';

    protected $description = 'Remove the oldest backups exceeding the backup_max_count setting';

    public function handle(PruneBackupsUseCase $pruner): int
    {
        $pruned = $pruner->prune();

        $this->info($pruned > 0
            ? sprintf('Pruned %d backup(s).', $pruned)
            : 'No backups to prune.');

        return self::SUCCESS;
    }
}
