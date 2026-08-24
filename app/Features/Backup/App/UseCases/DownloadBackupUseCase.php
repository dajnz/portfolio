<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\Domain\Entities\Backup;
use RuntimeException;

/**
 * The download guard (backup.md:423-430): only `completed` backups are
 * downloadable (backup.md:159). Returns the RustFS object key of the
 * archive — the controller streams it through the private `backups`
 * bucket behind the admin auth middleware.
 */
final class DownloadBackupUseCase
{
    public function download(Backup $backup): string
    {
        if (! $backup->canDownload()) {
            throw new RuntimeException('This backup is not downloadable.');
        }

        return $backup->toPath() ?? $backup->toFilename();
    }
}
