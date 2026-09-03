<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Repositories;

use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use App\Features\Backup\Infra\Mappers\BackupEncryptionKeyMapper;
use App\Features\Backup\Infra\Models\BackupEncryptionKeyModel;

/**
 * Eloquent-backed `backup_encryption_keys` storage (database.md:550-565).
 * The raw key is encrypted at rest (Crypt, under APP_KEY) at the
 * persistence boundary — the mapper encrypts on write and decrypts on
 * read (backup.md:495-497).
 */
final class EloquentEncryptionKeyRepository implements EncryptionKeyRepositoryInterface
{
    public function __construct(
        private readonly BackupEncryptionKeyMapper $mapper,
    ) {
    }

    public function create(EncryptionKey $key): EncryptionKey
    {
        $model = BackupEncryptionKeyModel::query()->create($this->mapper->toRow($key));

        return $this->mapper->toEntity($model);
    }

    public function save(EncryptionKey $key): void
    {
        BackupEncryptionKeyModel::query()
            ->whereKey($key->toId())
            ->update($this->mapper->toRow($key));
    }

    public function find(int $id): ?EncryptionKey
    {
        $model = BackupEncryptionKeyModel::query()->find($id);

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    public function findActive(): ?EncryptionKey
    {
        $model = BackupEncryptionKeyModel::query()->where('is_active', true)->first();

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    /**
     * @return list<EncryptionKey>
     */
    public function all(): array
    {
        return BackupEncryptionKeyModel::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BackupEncryptionKeyModel $model): EncryptionKey => $this->mapper->toEntity($model))
            ->all();
    }

    public function findByValue(string $rawKey): ?EncryptionKey
    {
        foreach (BackupEncryptionKeyModel::query()->get() as $model) {
            $key = $this->mapper->toEntity($model);

            if (hash_equals($key->toKeyValue(), $rawKey)) {
                return $key;
            }
        }

        return null;
    }

    public function delete(EncryptionKey $key): void
    {
        BackupEncryptionKeyModel::query()->whereKey($key->toId())->delete();
    }
}
