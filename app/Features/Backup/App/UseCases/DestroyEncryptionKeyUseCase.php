<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use RuntimeException;

use function sprintf;

/**
 * Key deletion (backup.md:513-514,589): a key referenced by any
 * backup cannot be deleted — the DB's `ON DELETE RESTRICT` enforces it
 * (database.md:587); the check here surfaces the "N backups reference
 * this key" message first (backup.md:589).
 */
final class DestroyEncryptionKeyUseCase
{
    public function __construct(
        private readonly EncryptionKeyRepositoryInterface $keys,
        private readonly BackupRepositoryInterface $backups,
    ) {
    }

    public function destroy(EncryptionKey $key): void
    {
        $references = $this->backups->countForEncryptionKey($key->toId());

        if ($references > 0) {
            throw new RuntimeException(
                sprintf('%d backup(s) reference this key — delete them first.', $references),
            );
        }

        $this->keys->delete($key);
    }
}
