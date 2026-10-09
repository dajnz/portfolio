<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * The queued backup job surface: dispatches the create/restore work
 * the admin endpoints trigger (backup.md:376, ADR-0018). An abstraction over
 * the infra `CreateBackupJob`/`RestoreBackupJob` so the app-layer use
 * cases never name the queue classes.
 */
interface BackupJobDispatcherInterface
{
    public function dispatchCreate(int $backupId): void;

    public function dispatchRestore(int $backupId): void;
}
