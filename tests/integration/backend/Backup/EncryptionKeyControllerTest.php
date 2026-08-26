<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;
use App\Features\Backup\Infra\Models\BackupEncryptionKeyModel;
use App\Features\Backup\Infra\Models\BackupModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fakes\FakeRustFsAssetCollector;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class)->group('backup');

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
    Storage::fake('rustfs-backups');
    app()->instance(
        AssetPathCollectorInterface::class,
        new FakeRustFsAssetCollector(app('filesystem')),
    );
    $this->loginAsAdmin($this->createAdmin());
});

describe('EncryptionKeyController', function (): void {
    it('lists the keys with their backup reference counts', function (): void {
        $key = BackupEncryptionKeyModel::factory()->create(['name' => 'initial-key', 'is_active' => true]);
        BackupEncryptionKeyModel::factory()->create(['name' => 'rotated-2026', 'is_active' => false]);
        BackupModel::factory()->create(['encryption_key_id' => $key->id]);

        $this->getJson('/admin/backups/encryption-keys')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'name' => 'initial-key',
                'is_active' => true,
                'backups_count' => 1,
            ])
            ->assertJsonFragment([
                'name' => 'rotated-2026',
                'is_active' => false,
                'backups_count' => 0,
            ]);
    });

    it('generates a key, returns the raw value once and rotates the active key', function (): void {
        BackupEncryptionKeyModel::factory()->create(['name' => 'old-active', 'is_active' => true]);

        $this->postJson('/admin/backups/encryption-keys', ['name' => 'rotated-2026'])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'key_value', 'is_active', 'created_at'],
            ])
            ->assertJsonPath('data.name', 'rotated-2026')
            ->assertJsonPath('data.is_active', true);

        $responseKey = $this->getJson('/admin/backups/encryption-keys')->json('data');

        $rawValues = collect($responseKey)->pluck('key_value')->filter()->all();

        expect($rawValues)->toBeEmpty()
            ->and(BackupEncryptionKeyModel::query()->where('name', 'old-active')->first()->is_active)->toBeFalse()
            ->and(BackupEncryptionKeyModel::query()->where('name', 'rotated-2026')->first()->is_active)->toBeTrue();
    });

    it('rejects a duplicate key name', function (): void {
        BackupEncryptionKeyModel::factory()->create(['name' => 'initial-key']);

        $this->postJson('/admin/backups/encryption-keys', ['name' => 'initial-key'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    });

    it('deletes a key with no referencing backups', function (): void {
        $key = BackupEncryptionKeyModel::factory()->create();

        $this->deleteJson("/admin/backups/encryption-keys/{$key->id}")
            ->assertNoContent();

        expect(BackupEncryptionKeyModel::query()->find($key->id))->toBeNull();
    });

    it('rejects deleting a key still referenced by backups', function (): void {
        $key = BackupEncryptionKeyModel::factory()->create();
        BackupModel::factory()->create(['encryption_key_id' => $key->id]);

        $this->deleteJson("/admin/backups/encryption-keys/{$key->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        expect(BackupEncryptionKeyModel::query()->find($key->id))->not->toBeNull();
    });

    it('rejects deleting a key referenced only by trashed backups (the FK sees every row)', function (): void {
        $key = BackupEncryptionKeyModel::factory()->create();
        $backup = BackupModel::factory()->create(['encryption_key_id' => $key->id]);
        $backup->delete();

        $this->deleteJson("/admin/backups/encryption-keys/{$key->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');

        expect(BackupEncryptionKeyModel::query()->find($key->id))->not->toBeNull();
    });
});
