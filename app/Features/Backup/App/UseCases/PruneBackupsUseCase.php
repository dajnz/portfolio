<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Services\BackupPruner;

/**
 * The retention entry point: delegates the surplus deletion to
 * `BackupPruner`.
 */
final class PruneBackupsUseCase
{
    public function __construct(
        private readonly BackupPruner $pruner,
    ) {
    }

    /**
     * @return int The number of pruned backups.
     */
    public function prune(?int $maxCount = null): int
    {
        return $this->pruner->prune($maxCount);
    }
}
