<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Contracts\StorageBatchTransferable;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;
use RuntimeException;

use function count;

/**
 * The post-create retention step: deletes the surplus backups when the
 * active count exceeds `backup_max_count` — the DB `settings` row wins
 * at runtime, the injected fallback (`config('backup.max_count')`,
 * resolved by the container) covers the pre-seed case. Oldest backups
 * are removed first; each record is soft-deleted and its archive
 * hard-deleted from the `backups` bucket via the storage contract.
 */
final class PruneBackupsUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
        private readonly SettingsReadRepositoryInterface $settings,
        private readonly StorageBatchTransferable $backupsTransfer,
        private readonly int $fallbackMaxCount,
    ) {
    }

    /**
     * @return int The number of pruned backups.
     */
    public function prune(?int $maxCount = null): int
    {
        $maxCount = $maxCount ?? (int) $this->settings->value('backup_max_count', $this->fallbackMaxCount);

        if ($maxCount < 0) {
            throw new RuntimeException('The backup_max_count setting must not be negative.');
        }

        $active = $this->backups->countActive();

        if ($active <= $maxCount) {
            return 0;
        }

        $surplus = $active - $maxCount;
        $oldest = $this->backups->oldestActive($surplus);

        foreach ($oldest as $backup) {
            $this->backups->delete($backup);
            $this->backupsTransfer->deleteObjects([$backup->toPath() ?? $backup->toFilename()]);
        }

        return count($oldest);
    }
}
