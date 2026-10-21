<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Commands;

use App\Features\Shared\App\UseCases\PruneTrashedItemsUseCase;
use Illuminate\Console\Command;

/**
 * Hard-deletes entities soft-deleted beyond the trash retention period
 * (default 30; 0 disables pruning). The retention policy and the
 * per-entity pruning live in `PruneTrashedItemsUseCase`; this command is
 * the monthly scheduler entry point.
 */
final class PruneTrashedItemsCommand extends Command
{
    protected $signature = 'app:prune-trashed-items';

    protected $description = 'Hard-delete entities soft-deleted beyond the trash retention period';

    public function handle(PruneTrashedItemsUseCase $prune): int
    {
        $result = $prune->prune();

        if ($result->retentionDays === 0) {
            $this->info('Trash pruning is disabled (trash_retention_days = 0).');

            return self::SUCCESS;
        }

        $this->info("Pruned {$result->deleted} trashed item(s) older than {$result->retentionDays} day(s).");

        return self::SUCCESS;
    }
}
