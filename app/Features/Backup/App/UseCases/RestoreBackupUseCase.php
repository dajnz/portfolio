<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Services\RestoreWorkflowService;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use App\Features\Backup\Domain\ValueObjects\BackupContentSummary;
use RuntimeException;
use Throwable;

/**
 * The restore execution (backup.md:393-408, A1): runs synchronously —
 * the CLI (`backup:restore`) calls it directly, the queued
 * `RestoreBackupJob` executes it inside the worker (the HTTP-triggered
 * restore's async shell). The record transitions
 * `completed → processing` (B298), the workflow performs the 10 steps,
 * then the record re-completes with its archive stamp; on any failure
 * the record goes `failed` and the site STAYS in maintenance mode
 * (backup.md:397) until `admin:reset-maintenance` or the Settings page.
 */
final class RestoreBackupUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
        private readonly EncryptionKeyRepositoryInterface $keys,
        private readonly RestoreWorkflowService $workflow,
        private readonly ?string $legacyEncryptionKey = null,
    ) {
    }

    /**
     * @throws Throwable On failure the record is marked `failed` and the
     *                   exception rethrown.
     */
    public function restore(Backup $backup): void
    {
        $backup->startProcessing();
        $this->backups->save($backup);

        try {
            $this->workflow->execute($backup, $this->resolveKey($backup));
        } catch (Throwable $exception) {
            $backup->fail();
            $this->backups->save($backup);

            throw $exception;
        }

        $backup->complete(
            $backup->toPath() ?? $backup->toFilename(),
            new BackupContentSummary(
                sizeBytes: $backup->toSizeBytes(),
                assetsCount: $backup->toAssetsCount(),
                assetsSizeBytes: $backup->toAssetsSizeBytes(),
                recordCounts: $backup->toRecordCounts(),
            ),
        );

        $this->backups->save($backup);
    }

    /**
     * Step 3 of the restore workflow (backup.md:399): the record's
     * `encryption_key_id` first (the `-k{id}` filename suffix tells the
     * admin which offline key to use), the legacy
     * `BACKUP_ENCRYPTION_KEY` env fallback for pre-encryption backups.
     */
    private function resolveKey(Backup $backup): string
    {
        $keyId = $backup->toEncryptionKeyId();

        if ($keyId !== null) {
            $key = $this->keys->find($keyId);

            if ($key === null) {
                throw new RuntimeException("The encryption key {$keyId} referenced by this backup no longer exists.");
            }

            return $key->toKeyValue();
        }

        if ($this->legacyEncryptionKey !== null && $this->legacyEncryptionKey !== '') {
            return $this->legacyEncryptionKey;
        }

        throw new RuntimeException('No encryption key is available for this backup.');
    }
}
