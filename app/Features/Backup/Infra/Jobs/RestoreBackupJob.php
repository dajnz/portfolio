<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Jobs;

use App\Features\Backup\App\UseCases\RestoreBackupUseCase;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * The queued async restore (ADR-0018): the HTTP-triggered restore dispatches
 * this job, which executes the synchronous `RestoreBackupUseCase`
 * inside the worker — the UI polls
 * `GET /admin/backups/{backup}/status` (~2 s) until the record
 * re-completes or fails. On failure the record is already marked
 * `failed` by the use case; this hook caches the error message for
 * the status endpoint (the `failed` row shape,
 * openapi/inertia/backup.yaml:64) and the site stays in maintenance
 * mode (backup.md:397).
 */
final class RestoreBackupJob implements ShouldQueue
{
    public const ERROR_CACHE_TTL_SECONDS = 86400;

    public function __construct(
        public readonly int $backupId,
    ) {
    }

    public function handle(
        BackupRepositoryInterface $backups,
        RestoreBackupUseCase $restore,
    ): void {
        $backup = $backups->find($this->backupId);

        if ($backup === null) {
            throw new RuntimeException("Backup {$this->backupId} no longer exists.");
        }

        $restore->restore($backup);
    }

    public function failed(Throwable $exception): void
    {
        Cache::put("backup:error:{$this->backupId}", $exception->getMessage(), self::ERROR_CACHE_TTL_SECONDS);
    }
}
