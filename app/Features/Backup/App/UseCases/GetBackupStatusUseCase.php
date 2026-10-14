<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\DTOs\BackupStatusDto;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\Exceptions\BackupNotFoundException;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;

/**
 * The status-polling read of one backup: the lifecycle state, the
 * completion timestamp and — for a `failed` record — the failure message
 * cached by the create/restore jobs.
 */
final class GetBackupStatusUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
    ) {
    }

    public function get(int $backupId): BackupStatusDto
    {
        $backup = $this->backups->find($backupId) ?? throw BackupNotFoundException::forId($backupId);

        return new BackupStatusDto(
            id: $backup->toId(),
            status: $backup->toStatus(),
            progress: null,
            completedAt: $backup->toCompletedAt(),
            errorMessage: $backup->toStatus() === BackupStatus::Failed
                ? $this->backups->errorMessageOf($backup->toId())
                : null,
        );
    }
}
