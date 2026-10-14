<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Contracts\BackupJobDispatcherInterface;
use App\Features\Backup\Domain\Exceptions\BackupNotFoundException;
use App\Features\Backup\Domain\Exceptions\BackupNotRestorableException;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;

/**
 * The HTTP-triggered restore: validates the backup is restorable and
 * schedules the async restore through the
 * {@see BackupJobDispatcherInterface} — the UI returns immediately and
 * polls the status endpoint.
 */
final class StartBackupRestoreUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
        private readonly BackupJobDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws BackupNotFoundException
     * @throws BackupNotRestorableException
     */
    public function start(int $backupId): void
    {
        $backup = $this->backups->find($backupId) ?? throw BackupNotFoundException::forId($backupId);

        if (! $backup->canDownload()) {
            throw BackupNotRestorableException::forId($backupId);
        }

        $this->dispatcher->dispatchRestore($backupId);
    }
}
