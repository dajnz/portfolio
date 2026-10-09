<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\Contracts\BackupJobDispatcherInterface;
use App\Features\Backup\App\Services\BackupFilename;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use App\Features\Backup\Domain\ValueObjects\BackupContentSummary;
use App\Features\Backup\Domain\ValueObjects\BackupTimings;
use DateTimeImmutable;

/**
 * The "Create Backup Now" entry point (backup.md:376,460): creates the
 * `pending` `backups` row (the archive's final filename — ADR-0018's
 * `-k{id}` suffix from the active key) and dispatches the create job
 * via the {@see BackupJobDispatcherInterface}, so the admin UI returns
 * immediately and the row is polled via `GET /admin/backups/{backup}/status`.
 * When encryption is enabled but no active key exists, the row is
 * created anyway — the job fails it at pre-flight (backup.md:389) and
 * the UI shows the key-generation prompt. The `encryption_enabled`
 * flag is injected (the container resolves it from
 * `config('backup.encryption_enabled')`) — the app layer reads no
 * config directly.
 */
final class CreateBackupUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
        private readonly EncryptionKeyRepositoryInterface $keys,
        private readonly BackupJobDispatcherInterface $dispatcher,
        private readonly bool $encryptionEnabled,
    ) {
    }

    public function create(): Backup
    {
        $key = $this->encryptionEnabled ? $this->keys->findActive() : null;
        $now = new DateTimeImmutable();

        $backup = new Backup(
            id: 0,
            filename: BackupFilename::for($now, $key?->toId(), $key?->toName()),
            path: null,
            summary: new BackupContentSummary(null, null, null, null),
            status: BackupStatus::Pending,
            encryptionKeyId: $key?->toId(),
            timings: new BackupTimings($now, null, null),
        );

        $created = $this->backups->create($backup);

        $this->dispatcher->dispatchCreate($created->toId());

        return $created;
    }
}
