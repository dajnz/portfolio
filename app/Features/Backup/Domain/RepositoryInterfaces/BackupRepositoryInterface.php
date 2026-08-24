<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\RepositoryInterfaces;

use App\Features\Backup\Domain\Entities\Backup;

/**
 * Persistence for the `backups` table (database.md:567-587). The
 * repository stores and materializes {@see Backup} entities — the
 * entity's state transitions are persisted via `save()`, new pending
 * records are created via `create()` (the workflows never touch the
 * model directly).
 */
interface BackupRepositoryInterface
{
    /**
     * Inserts a new record (the pending row created by the
     * `backup:create` command / "Create Backup Now" button) and
     * returns the entity with the generated id.
     */
    public function create(Backup $backup): Backup;

    /**
     * Persists the entity's current state — used after every state
     * transition (`startProcessing()`, `complete()`, `fail()`).
     */
    public function save(Backup $backup): void;

    public function find(int $id): ?Backup;

    /**
     * The restore target of `backup:restore {filename}`
     * (backup.md:459).
     */
    public function findByFilename(string $filename): ?Backup;

    /**
     * The non-trashed backups, newest first — the admin list.
     *
     * @return list<Backup>
     */
    public function listActive(): array;

    /**
     * The number of non-trashed backups — the prune decision source
     * (backup.md:460): when the count exceeds the `backup_max_count`
     * setting, the oldest records are deleted.
     */
    public function countActive(): int;

    /**
     * The oldest non-trashed backups, oldest first — the prune
     * candidates (the workflow deletes the surplus).
     *
     * @return list<Backup>
     */
    public function oldestActive(int $limit): array;

    /**
     * How many non-trashed backups reference the encryption key — the
     * admin UI's "N backups reference this key" tooltip and the key
     * deletion guard (database.md:587, backup.md:510).
     */
    public function countForEncryptionKey(int $encryptionKeyId): int;

    /**
     * Soft-deletes the record (deleted_at) — the archive's RustFS
     * hard-delete is the caller's concern (backup.md:157,435).
     */
    public function delete(Backup $backup): void;
}
