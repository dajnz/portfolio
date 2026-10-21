<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Services\BackupWorkflowService;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\Exceptions\BackupNotFoundException;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use App\Features\Shared\App\Contracts\LoggerInterface;
use RuntimeException;
use Throwable;

use function in_array;

/**
 * The queued backup execution: picks up the `pending` row, marks it
 * `processing`, runs the {@see BackupWorkflowService} and marks it
 * `failed` on any error before rethrowing. The encryption key comes from
 * the record's `encryption_key_id` (`-k{id}` filename suffix), so a key
 * rotation between dispatch and execution cannot change it.
 */
final class RunBackupUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
        private readonly EncryptionKeyRepositoryInterface $keys,
        private readonly BackupWorkflowService $workflow,
        private readonly LoggerInterface $logger,
        private readonly bool $encryptionEnabled,
    ) {
    }

    /**
     * @throws Throwable on failure the record is marked `failed` and the
     *                   exception rethrown
     */
    public function run(int $backupId): void
    {
        $backup = $this->backups->find($backupId)
            ?? throw BackupNotFoundException::forId($backupId);

        try {
            $key = $this->resolveKey($backup);

            $backup->startProcessing();
            $this->backups->save($backup);

            $this->workflow->execute($backup, $key?->toKeyValue() ?? '', $key?->toId());
        } catch (Throwable $exception) {
            $this->markFailed($backupId);

            $this->logger->error('Backup failed', [
                'archive_name' => $backup->toFilename(),
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function resolveKey(Backup $backup): ?EncryptionKey
    {
        $keyId = $backup->toEncryptionKeyId();

        if ($keyId === null) {
            if ($this->encryptionEnabled) {
                throw new RuntimeException('No active backup encryption key — generate one first.');
            }

            return null;
        }

        $key = $this->keys->find($keyId);

        if ($key === null) {
            throw new RuntimeException("The encryption key {$keyId} referenced by this backup no longer exists.");
        }

        return $key;
    }

    private function markFailed(int $backupId): void
    {
        $current = $this->backups->find($backupId);

        if ($current !== null && in_array($current->toStatus(), [BackupStatus::Pending, BackupStatus::Processing], true)) {
            $current->fail();
            $this->backups->save($current);
        }
    }
}
