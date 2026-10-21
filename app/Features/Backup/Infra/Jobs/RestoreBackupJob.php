<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Jobs;

use App\Features\Backup\App\UseCases\RestoreBackupUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The queued async restore shell: the HTTP-triggered restore dispatches
 * this job, which executes the synchronous `RestoreBackupUseCase` inside
 * the worker — the UI polls `GET /admin/backups/{backup}/status` (~2 s)
 * until the record re-completes or fails. The `failed()` hook caches the
 * error message for the status endpoint.
 */
final class RestoreBackupJob implements ShouldQueue
{
    public const ERROR_CACHE_TTL_SECONDS = 86400;

    public function __construct(
        public readonly int $backupId,
    ) {
    }

    public function handle(RestoreBackupUseCase $restore): void
    {
        $restore->restoreById($this->backupId);
    }

    public function failed(Throwable $exception): void
    {
        Cache::put("backup:error:{$this->backupId}", $exception->getMessage(), self::ERROR_CACHE_TTL_SECONDS);
    }
}
