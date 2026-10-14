<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\DTOs\BackupDownloadDto;
use App\Features\Backup\Domain\Exceptions\BackupNotFoundException;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use RuntimeException;

/**
 * The download guard: only `completed` backups are downloadable. Returns
 * the RustFS object key of the archive and its display filename — the
 * controller streams it through the private `backups` bucket behind the
 * admin auth middleware.
 */
final class DownloadBackupUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
    ) {
    }

    public function download(int $backupId): BackupDownloadDto
    {
        $backup = $this->backups->find($backupId) ?? throw BackupNotFoundException::forId($backupId);

        if (! $backup->canDownload()) {
            throw new RuntimeException('This backup is not downloadable.');
        }

        return new BackupDownloadDto(
            path: $backup->toPath() ?? $backup->toFilename(),
            filename: $backup->toFilename(),
        );
    }
}
