<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Contracts\StorageBatchTransferable;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use RuntimeException;

/**
 * The delete action (backup.md:432-439): processing backups cannot be
 * deleted (backup.md:159). The DB record is soft-deleted (the audit
 * trail), the archive is hard-deleted from the `backups` bucket via
 * the storage contract — a later download of the record 404s
 * (backup.md:157,435-438).
 */
final class DestroyBackupUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
        private readonly StorageBatchTransferable $backupsTransfer,
    ) {
    }

    public function destroy(Backup $backup): void
    {
        if (! $backup->canDelete()) {
            throw new RuntimeException('This backup is currently being processed and cannot be deleted.');
        }

        $this->backups->delete($backup);
        $this->backupsTransfer->deleteObjects([$backup->toPath() ?? $backup->toFilename()]);
    }
}
