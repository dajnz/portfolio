<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\ValueObjects\BackupContentSummary;
use App\Features\Backup\Domain\ValueObjects\BackupTimings;
use App\Features\Backup\Infra\Models\BackupModel;
use DateTimeImmutable;
use Tests\Support\Traits\InteractsWithBackupStorage;

uses(InteractsWithBackupStorage::class)->group('backup');

function pendingBackupEntity(?int $keyId = null): Backup
{
    $now = new DateTimeImmutable('2026-08-24 10:00:00');

    return new Backup(
        id: 0,
        filename: 'backup-2026-08-24-100000-k1.zip',
        path: null,
        summary: new BackupContentSummary(null, null, null, null),
        status: BackupStatus::Pending,
        encryptionKeyId: $keyId,
        timings: new BackupTimings($now, null, null),
    );
}

describe('EloquentBackupRepository', function (): void {
    it('stores a pending row with the NOT NULL placeholders', function (): void {
        $key = \App\Features\Backup\Infra\Models\BackupEncryptionKeyModel::factory()->create();
        $repository = app(BackupRepositoryInterface::class);

        $created = $repository->create(pendingBackupEntity($key->id));

        $row = BackupModel::query()->find($created->toId());

        expect($row->path)->toBe('')
            ->and($row->size_bytes)->toBe(0)
            ->and($row->status)->toBe('pending')
            ->and($row->completed_at)->toBeNull();
    });

    it('materializes a pending row with null stamps', function (): void {
        $key = \App\Features\Backup\Infra\Models\BackupEncryptionKeyModel::factory()->create();
        $repository = app(BackupRepositoryInterface::class);
        $created = $repository->create(pendingBackupEntity($key->id));

        $found = $repository->find($created->toId());

        expect($found->toStatus())->toBe(BackupStatus::Pending)
            ->and($found->toPath())->toBeNull()
            ->and($found->toSizeBytes())->toBeNull()
            ->and($found->toCompletedAt())->toBeNull();
    });

    it('persists the processing transition', function (): void {
        $repository = app(BackupRepositoryInterface::class);
        $created = $repository->create(pendingBackupEntity());

        $created->startProcessing();
        $repository->save($created);

        $row = BackupModel::query()->find($created->toId());

        expect($row->status)->toBe('processing')
            ->and($row->completed_at)->toBeNull();
    });

    it('persists the completed stamp with the archive metadata', function (): void {
        $repository = app(BackupRepositoryInterface::class);
        $created = $repository->create(pendingBackupEntity());
        $created->startProcessing();
        $repository->save($created);

        $created->complete('backup-2026-08-24-100000-k1.zip', new BackupContentSummary(
            12345,
            2,
            999,
            ['settings' => 16],
        ));
        $repository->save($created);

        $found = $repository->find($created->toId());

        expect($found->canDownload())->toBeTrue()
            ->and($found->toPath())->toBe('backup-2026-08-24-100000-k1.zip')
            ->and($found->toSizeBytes())->toBe(12345)
            ->and($found->toAssetsCount())->toBe(2)
            ->and($found->toRecordCounts())->toBe(['settings' => 16])
            ->and($found->toCompletedAt())->not->toBeNull();
    });

    it('lists active backups newest first', function (): void {
        $repository = app(BackupRepositoryInterface::class);

        BackupModel::factory()->create([
            'filename' => 'backup-2026-01-01-020000-k1.zip',
            'path' => 'backup-2026-01-01-020000-k1.zip',
            'created_at' => '2026-01-01 02:00:00',
            'completed_at' => '2026-01-01 02:00:05',
        ]);
        BackupModel::factory()->create([
            'filename' => 'backup-2026-02-01-020000-k1.zip',
            'path' => 'backup-2026-02-01-020000-k1.zip',
            'created_at' => '2026-02-01 02:00:00',
            'completed_at' => '2026-02-01 02:00:05',
        ]);
        BackupModel::factory()->create([
            'filename' => 'backup-2025-12-01-020000-k1.zip',
            'path' => 'backup-2025-12-01-020000-k1.zip',
            'created_at' => '2025-12-01 02:00:00',
            'completed_at' => '2025-12-01 02:00:05',
        ])->delete();

        $list = $repository->listActive();

        expect($list)->toHaveCount(2)
            ->and($list[0]->toFilename())->toBe('backup-2026-02-01-020000-k1.zip');
    });

    it('counts only non-trashed backups', function (): void {
        $repository = app(BackupRepositoryInterface::class);

        BackupModel::factory()->create();
        BackupModel::factory()->create()->delete();

        expect($repository->countActive())->toBe(1)
            ->and($repository->oldestActive(1))->toHaveCount(1);
    });

    it('counts backups referencing a key and excludes trashed rows', function (): void {
        $key = \App\Features\Backup\Infra\Models\BackupEncryptionKeyModel::factory()->create();

        BackupModel::factory()->create(['encryption_key_id' => $key->id]);
        BackupModel::factory()->create(['encryption_key_id' => $key->id]);
        BackupModel::factory()->create(['encryption_key_id' => $key->id])->delete();
        BackupModel::factory()->create(['encryption_key_id' => null]);

        $repository = app(BackupRepositoryInterface::class);

        expect($repository->countForEncryptionKey($key->id))->toBe(2);
    });

    it('detaches trashed backups from an encryption key', function (): void {
        $key = \App\Features\Backup\Infra\Models\BackupEncryptionKeyModel::factory()->create();

        $live = BackupModel::factory()->create(['encryption_key_id' => $key->id]);
        $trashed = BackupModel::factory()->create(['encryption_key_id' => $key->id]);
        $trashed->delete();

        $repository = app(BackupRepositoryInterface::class);
        $repository->detachTrashedEncryptionKeyReferences($key->id);

        expect(BackupModel::withTrashed()->find($trashed->id)->encryption_key_id)->toBeNull()
            ->and(BackupModel::find($live->id)->encryption_key_id)->toBe($key->id);
    });

    it('soft-deletes records so reads return null', function (): void {
        $repository = app(BackupRepositoryInterface::class);
        $entity = BackupModel::factory()->create();

        $repository->delete($repository->find($entity->id));

        expect(BackupModel::withTrashed()->find($entity->id)->deleted_at)->not->toBeNull()
            ->and($repository->find($entity->id))->toBeNull()
            ->and($repository->findByFilename($entity->filename))->toBeNull();
    });
});
