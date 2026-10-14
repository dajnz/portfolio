<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\Domain\Exceptions\EncryptionKeyNotFoundException;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use RuntimeException;

use function sprintf;

/**
 * Key deletion: a key referenced by any backup cannot be deleted — the
 * DB's `ON DELETE RESTRICT` enforces it; the check here surfaces the
 * "N backups reference this key" message first.
 */
final class DestroyEncryptionKeyUseCase
{
    public function __construct(
        private readonly EncryptionKeyRepositoryInterface $keys,
        private readonly BackupRepositoryInterface $backups,
    ) {
    }

    public function destroy(int $keyId): void
    {
        $key = $this->keys->find($keyId) ?? throw EncryptionKeyNotFoundException::forId($keyId);
        $references = $this->backups->countForEncryptionKey($key->toId());

        if ($references > 0) {
            throw new RuntimeException(
                sprintf('%d backup(s) reference this key — delete them first.', $references),
            );
        }

        // Only live rows are counted above — trashed rows still pin the
        // key via the FK, so detach them before the delete.
        $this->backups->detachTrashedEncryptionKeyReferences($key->toId());
        $this->keys->delete($key);
    }
}
