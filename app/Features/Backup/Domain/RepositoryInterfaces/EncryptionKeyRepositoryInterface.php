<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\RepositoryInterfaces;

use App\Features\Backup\Domain\Entities\EncryptionKey;

/**
 * Persistence for `backup_encryption_keys` (database.md:550-565).
 * `key_value` is encrypted at rest via `Crypt` — the repository
 * encrypts on write and decrypts on read, so the entity always
 * carries the raw key value (backup.md:494-495).
 */
interface EncryptionKeyRepositoryInterface
{
    /**
     * Inserts a new key row and returns the entity with the generated
     * id. The exactly-one-active invariant is enforced by the
     * generation use case (database.md:563), which deactivates the
     * previous active key before creating the new one.
     */
    public function create(EncryptionKey $key): EncryptionKey;

    /**
     * Persists the entity's current state — used after
     * `activate()`/`deactivate()` (key rotation, backup.md:508-509).
     */
    public function save(EncryptionKey $key): void;

    public function find(int $id): ?EncryptionKey;

    /**
     * The active key — the create workflow's encryption key
     * (backup.md:496); null when none exists yet.
     */
    public function findActive(): ?EncryptionKey;

    /**
     * Hard-deletes the row — the DB's `ON DELETE RESTRICT` on
     * `backups.encryption_key_id` rejects it while backups still
     * reference the key (database.md:587); the use case checks
     * `BackupRepositoryInterface::countForEncryptionKey()` first.
     */
    public function delete(EncryptionKey $key): void;
}
