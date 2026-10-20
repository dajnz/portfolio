<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Services\BackupImporter;
use App\Features\Backup\Domain\Entities\Backup;

/**
 * The disaster-recovery import entry point: delegates the archive
 * validation, upload and record creation to `BackupImporter`. Called via the
 * admin UI upload form or `backup:restore --path --key` CLI.
 */
final class ImportFromPathUseCase
{
    public function __construct(
        private readonly BackupImporter $importer,
    ) {
    }

    public function import(string $path, string $rawKey): Backup
    {
        return $this->importer->import($path, $rawKey);
    }
}
