<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Services;

use App\Features\Backup\App\Contracts\BackupJobDispatcherInterface;
use App\Features\Backup\Infra\Jobs\CreateBackupJob;
use App\Features\Backup\Infra\Jobs\RestoreBackupJob;
use Illuminate\Support\Facades\Bus;

/**
 * The queue-backed job dispatcher behind the
 * `BackupJobDispatcherInterface`: dispatches `CreateBackupJob`
 * (backup.md:376) and `RestoreBackupJob` (ADR-0018's async restore shell).
 */
final class QueueBackupJobDispatcher implements BackupJobDispatcherInterface
{
    public function dispatchCreate(int $backupId): void
    {
        Bus::dispatch(new CreateBackupJob($backupId));
    }

    public function dispatchRestore(int $backupId): void
    {
        Bus::dispatch(new RestoreBackupJob($backupId));
    }
}
