<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Repositories;

use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Infra\Mappers\BackupMapper;
use App\Features\Backup\Infra\Models\BackupModel;

/**
 * Eloquent-backed `backups` storage (database.md:567-587). All
 * writes go through the model (the mapper prepares the rows — the
 * pending `''`/`0` placeholders); reads materialize the entities via
 * the mapper. Soft-deleted rows are invisible to every read — a
 * deleted backup's download/status lookups 404 (backup.md:438).
 */
final class EloquentBackupRepository implements BackupRepositoryInterface
{
    public function __construct(
        private readonly BackupMapper $mapper,
    ) {
    }

    public function create(Backup $backup): Backup
    {
        $model = BackupModel::query()->create($this->mapper->toRow($backup));

        return $this->mapper->toEntity($model);
    }

    public function save(Backup $backup): void
    {
        BackupModel::query()
            ->whereKey($backup->toId())
            ->whereNull('deleted_at')
            ->update($this->mapper->toRow($backup));
    }

    public function find(int $id): ?Backup
    {
        $model = BackupModel::query()->find($id);

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    public function findByFilename(string $filename): ?Backup
    {
        $model = BackupModel::query()->where('filename', $filename)->first();

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    /**
     * @return list<Backup>
     */
    public function listActive(): array
    {
        return BackupModel::query()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BackupModel $model): Backup => $this->mapper->toEntity($model))
            ->all();
    }

    public function countActive(): int
    {
        return BackupModel::query()->count();
    }

    /**
     * @return list<Backup>
     */
    public function oldestActive(int $limit): array
    {
        return BackupModel::query()
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (BackupModel $model): Backup => $this->mapper->toEntity($model))
            ->all();
    }

    public function countForEncryptionKey(int $encryptionKeyId): int
    {
        return BackupModel::query()
            ->where('encryption_key_id', $encryptionKeyId)
            ->count();
    }

    public function detachTrashedEncryptionKeyReferences(int $encryptionKeyId): void
    {
        // Soft-deleted rows are invisible to the admin yet still pin the
        // key via the FK `ON DELETE RESTRICT` — key destruction detaches
        // them first (the trashed archive keeps its `-k{id}--{name}`
        // filename for manual decryption).
        BackupModel::withTrashed()
            ->where('encryption_key_id', $encryptionKeyId)
            ->whereNotNull('deleted_at')
            ->update(['encryption_key_id' => null]);
    }

    public function delete(Backup $backup): void
    {
        $model = BackupModel::query()->find($backup->toId());

        $model?->delete();
    }
}
