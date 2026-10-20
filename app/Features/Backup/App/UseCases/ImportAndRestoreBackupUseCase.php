<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Contracts\BackupJobDispatcherInterface;
use App\Features\Backup\App\Services\BackupImporter;

/**
 * The disaster-recovery upload flow: imports the archive (validating it
 * against the offline key) and schedules the async restore — one action
 * from the admin's point of view.
 */
final class ImportAndRestoreBackupUseCase
{
    public function __construct(
        private readonly BackupImporter $importer,
        private readonly BackupJobDispatcherInterface $dispatcher,
    ) {
    }

    public function importAndRestore(string $path, string $rawKey): void
    {
        $backup = $this->importer->import($path, $rawKey);

        $this->dispatcher->dispatchRestore($backup->toId());
    }
}
