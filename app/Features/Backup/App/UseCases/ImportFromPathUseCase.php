<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Exceptions\ImportValidationException;
use App\Features\Backup\App\Contracts\BackupArchiveInterface;
use App\Features\Backup\App\Contracts\StorageBatchTransferable;
use App\Features\Backup\App\Services\BackupFilename;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use App\Features\Backup\Domain\ValueObjects\BackupContentSummary;
use App\Features\Backup\Domain\ValueObjects\BackupTimings;
use DateTimeImmutable;
use RuntimeException;

use function filesize;
use function is_file;
use function is_readable;

/**
 * The disaster-recovery import: validates a downloaded `.zip` against a
 * raw offline-saved key, resolves the matching stored key —
 * `EncryptionKeyRepositoryInterface::findByValue()` first (a key the
 * archive was encrypted with may already exist in
 * `backup_encryption_keys`, e.g. re-importing a backup downloaded from
 * this same instance — reusing it avoids the duplicate row), importing
 * the key only when no match exists (`is_active = false` — it serves
 * this backup only, never future creations; the naming convention lives
 * on `EncryptionKey::forImport()`), uploads the archive to the `backups`
 * bucket under the `backup-import-...-k{id}--{name}.zip` name and
 * creates the `completed` `backups` record from the manifest. The caller
 * passes the returned entity to the restore flow.
 */
final class ImportFromPathUseCase
{
    public function __construct(
        private readonly BackupArchiveInterface $archive,
        private readonly EncryptionKeyRepositoryInterface $keys,
        private readonly BackupRepositoryInterface $backups,
        private readonly StorageBatchTransferable $backupsTransfer,
    ) {
    }

    public function import(string $path, string $rawKey): Backup
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new ImportValidationException('The uploaded file is not a valid backup archive.');
        }

        try {
            $manifest = $this->archive->validate($path, $rawKey);
        } catch (RuntimeException $exception) {
            throw new ImportValidationException(
                'The encryption key does not match this backup or the archive is corrupt.',
                previous: $exception,
            );
        }

        $now = new DateTimeImmutable();
        $key = $this->keys->findByValue($rawKey) ?? $this->keys->create(EncryptionKey::forImport($rawKey, $now));

        $filename = BackupFilename::forImport($now, $key->toId(), $key->toName());
        $this->backupsTransfer->uploadBatch([$path => $filename]);

        return $this->backups->create(new Backup(
            id: 0,
            filename: $filename,
            path: $filename,
            summary: new BackupContentSummary(
                sizeBytes: (int) filesize($path),
                assetsCount: $manifest->toAssetsCount(),
                assetsSizeBytes: $manifest->toAssetsSizeBytes(),
                recordCounts: $manifest->toRecordCounts(),
            ),
            status: BackupStatus::Completed,
            encryptionKeyId: $key->toId(),
            timings: new BackupTimings($now, $now, null),
        ));
    }
}
