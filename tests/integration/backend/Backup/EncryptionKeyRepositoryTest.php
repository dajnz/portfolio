<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use App\Features\Backup\Infra\Models\BackupEncryptionKeyModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\Crypt;

uses()->group('backup');

describe('EloquentEncryptionKeyRepository', function (): void {
    it('encrypts the raw key at rest and decrypts it back on read', function (): void {
        $repository = app(EncryptionKeyRepositoryInterface::class);
        $now = new DateTimeImmutable('2026-08-24 10:00:00');

        $created = $repository->create(new EncryptionKey(
            id: 0,
            name: 'initial-key',
            keyValue: 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
            isActive: false,
            createdAt: $now,
            updatedAt: $now,
        ));

        $row = BackupEncryptionKeyModel::query()->find($created->toId());

        expect($row->key_value)->not->toBe('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')
            ->and($created->toKeyValue())->toBe('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')
            ->and($repository->find($created->toId())->toKeyValue())
                ->toBe('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');
    });

    it('finds the active key and lists all keys newest first', function (): void {
        $repository = app(EncryptionKeyRepositoryInterface::class);

        BackupEncryptionKeyModel::factory()->create(['name' => 'older', 'is_active' => false, 'created_at' => '2026-01-01 00:00:00']);
        BackupEncryptionKeyModel::factory()->create(['name' => 'newer', 'is_active' => true, 'created_at' => '2026-02-01 00:00:00']);

        expect($repository->findActive()->toName())->toBe('newer');

        $all = $repository->all();

        expect($all)->toHaveCount(2)
            ->and($all[0]->toName())->toBe('newer');
    });

    it('persists the active flag change', function (): void {
        $repository = app(EncryptionKeyRepositoryInterface::class);
        $entity = BackupEncryptionKeyModel::factory()->create(['is_active' => true]);

        $entity = $repository->find($entity->id);
        $entity->deactivate();
        $repository->save($entity);

        expect($repository->find($entity->toId())->isActive())->toBeFalse()
            ->and($repository->findActive())->toBeNull();
    });

    it('finds a stored key by its raw value — the DR reuse check', function (): void {
        $repository = app(EncryptionKeyRepositoryInterface::class);
        $raw = 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=';

        BackupEncryptionKeyModel::factory()->create(['name' => 'initial-key', 'key_value' => Crypt::encryptString($raw)]);
        BackupEncryptionKeyModel::factory()->create([
            'name' => 'rotated-2026',
            'key_value' => Crypt::encryptString('YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXowMTIzNDU='),
        ]);

        $match = $repository->findByValue($raw);

        expect($match)->not->toBeNull()
            ->and($match->toName())->toBe('initial-key')
            ->and($match->toKeyValue())->toBe($raw)
            ->and($repository->findByValue('YWJjZGVmZ2hpamtsbW5vcHFyc3R1dnd4eXowMTIzNDU=')->toName())->toBe('rotated-2026')
            ->and($repository->findByValue('ZmVkY2JhOTg3NjU0MzIxMGZlZGNiYTk4NzY1NDMyMTA='))->toBeNull();
    });

    it('hard-deletes the row', function (): void {
        $repository = app(EncryptionKeyRepositoryInterface::class);
        $entity = BackupEncryptionKeyModel::factory()->create();

        $repository->delete($repository->find($entity->id));

        expect(BackupEncryptionKeyModel::query()->find($entity->id))->toBeNull();
    });
});
