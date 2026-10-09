<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\RepositoryInterfaces;

use App\Features\Backup\Domain\Entities\EncryptionKey;

/**
 * Persistence for `backup_encryption_keys`. `key_value` is encrypted at
 * rest via `Crypt` — the repository encrypts on write and decrypts on
 * read, so the entity always carries the raw key value.
 */
interface EncryptionKeyRepositoryInterface
{
    /**
     * Inserts a new key row and returns the entity with the generated
     * id. The exactly-one-active invariant is enforced by the
     * generation use case, which deactivates the previous active key
     * before creating the new one.
     */
    public function create(EncryptionKey $key): EncryptionKey;

    /**
     * Persists the entity's current state — used after
     * `activate()`/`deactivate()` (key rotation).
     */
    public function save(EncryptionKey $key): void;

    public function find(int $id): ?EncryptionKey;

    /**
     * The active key — the create workflow's encryption key; null when
     * none exists yet.
     */
    public function findActive(): ?EncryptionKey;

    /**
     * Every key, newest first — the admin keys list.
     *
     * @return list<EncryptionKey>
     */
    public function all(): array;

    /**
     * The stored key whose raw value equals `$rawKey`, null when no
     * such key exists. The DR import checks this first and reuses the
     * existing key instead of importing a duplicate. The ciphertext is
     * non-deterministic (Crypt salts per row), so the match decrypts
     * and compares in PHP.
     */
    public function findByValue(string $rawKey): ?EncryptionKey;

    /**
     * Hard-deletes the row — the DB's `ON DELETE RESTRICT` on
     * `backups.encryption_key_id` rejects it while backups still
     * reference the key; the use case checks
     * `BackupRepositoryInterface::countForEncryptionKey()` first.
     */
    public function delete(EncryptionKey $key): void;
}
