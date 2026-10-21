<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Jobs;

use App\Features\Backup\App\UseCases\RunBackupUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The queued backup creation shell: the pending row, key resolution,
 * status transitions and workflow execution live in
 * {@see RunBackupUseCase}. The `failed()` hook caches the error message
 * for the status endpoint.
 */
final class CreateBackupJob implements ShouldQueue
{
    public const ERROR_CACHE_TTL_SECONDS = 86400;

    public function __construct(
        public readonly int $backupId,
    ) {
    }

    public function handle(RunBackupUseCase $run): void
    {
        $run->run($this->backupId);
    }

    public function failed(Throwable $exception): void
    {
        Cache::put("backup:error:{$this->backupId}", $exception->getMessage(), self::ERROR_CACHE_TTL_SECONDS);
    }
}
