<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Jobs;

use App\Features\Backup\App\Services\BackupWorkflowService;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

use function config;
use function in_array;

/**
 * The queued backup creation (backup.md:376,460): picked up from the
 * `pending` row, marks it `processing`, runs the 10-step
 * `BackupWorkflowService` (which completes it) and on any failure
 * marks the record `failed` with the error message cached for the
 * status endpoint (the `failed` row shape, openapi/inertia/backup.yaml:64).
 * The encryption key comes from the record's `encryption_key_id` (the
 * `-k{id}` filename suffix — a rotation between dispatch and execution
 * must not change the key); when the record has no key id and
 * encryption is enabled, the job fails — no active key existed at
 * creation (backup.md:389).
 */
final class CreateBackupJob implements ShouldQueue
{
    public const ERROR_CACHE_TTL_SECONDS = 86400;

    public function __construct(
        public readonly int $backupId,
    ) {
    }

    public function handle(
        BackupRepositoryInterface $backups,
        EncryptionKeyRepositoryInterface $keys,
        BackupWorkflowService $workflow,
    ): void {
        $backup = $backups->find($this->backupId);

        if ($backup === null) {
            throw new RuntimeException("Backup {$this->backupId} no longer exists.");
        }

        try {
            $key = $this->resolveKey($backup, $keys);

            $backup->startProcessing();
            $backups->save($backup);

            $workflow->execute($backup, $key?->toKeyValue() ?? '', $key?->toId());
        } catch (Throwable $exception) {
            $current = $backups->find($this->backupId);

            if ($current !== null && in_array($current->toStatus(), [BackupStatus::Pending, BackupStatus::Processing], true)) {
                $current->fail();
                $backups->save($current);
            }

            Log::error('Backup failed', [
                'archive_name' => $backup->toFilename(),
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function resolveKey(
        Backup $backup,
        EncryptionKeyRepositoryInterface $keys,
    ): ?EncryptionKey {
        $keyId = $backup->toEncryptionKeyId();

        if ($keyId === null) {
            if (config('backup.encryption_enabled')) {
                throw new RuntimeException('No active backup encryption key — generate one first.');
            }

            return null;
        }

        $key = $keys->find($keyId);

        if ($key === null) {
            throw new RuntimeException("The encryption key {$keyId} referenced by this backup no longer exists.");
        }

        return $key;
    }

    public function failed(Throwable $exception): void
    {
        Cache::put("backup:error:{$this->backupId}", $exception->getMessage(), self::ERROR_CACHE_TTL_SECONDS);
    }
}
