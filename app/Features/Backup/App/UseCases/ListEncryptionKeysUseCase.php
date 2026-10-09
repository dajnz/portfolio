<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\DTOs\EncryptionKeyListItemDto;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;

/**
 * The `GET /admin/backups/encryption-keys` listing: every key, newest
 * first, with the reference count.
 *
 * @return list<EncryptionKeyListItemDto>
 */
final class ListEncryptionKeysUseCase
{
    public function __construct(
        private readonly EncryptionKeyRepositoryInterface $keys,
        private readonly BackupRepositoryInterface $backups,
    ) {
    }

    public function list(): array
    {
        $keys = $this->keys->all();

        return array_map(
            fn ($key): EncryptionKeyListItemDto => new EncryptionKeyListItemDto(
                id: $key->toId(),
                name: $key->toName(),
                createdAt: $key->toCreatedAt(),
                isActive: $key->isActive(),
                backupsCount: $this->backups->countForEncryptionKey($key->toId()),
            ),
            $keys,
        );
    }
}
