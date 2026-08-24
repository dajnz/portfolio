<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;
use App\Features\Backup\App\UseCases\CreateBackupUseCase;
use App\Features\Backup\App\UseCases\GenerateEncryptionKeyUseCase;
use App\Features\Backup\Domain\Entities\Backup;
use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fakes\FakeRustFsAssetCollector;

/**
 * Shared environment of the Backup integration suites: the settings
 * seed, the two faked RustFS disks (the CI has no RustFS service —
 * Storage::fake; the real collector talks to the S3 client the fake
 * cannot serve, so the disk-driven collector fake is bound) and the
 * completed-backup creation helpers.
 */
trait InteractsWithBackupStorage
{
    protected function seedBackupEnvironment(): void
    {
        $this->seed(SettingsSeeder::class);
        Storage::fake('rustfs');
        Storage::fake('rustfs-backups');
        app()->instance(
            AssetPathCollectorInterface::class,
            new FakeRustFsAssetCollector(app('filesystem')),
        );
    }

    /**
     * The active encryption key — generated once per test when none
     * exists yet (idempotent across repeated helper calls).
     */
    protected function activeEncryptionKey(string $name = 'initial-key'): EncryptionKey
    {
        $keys = app(EncryptionKeyRepositoryInterface::class);

        if ($keys->findActive() === null) {
            app(GenerateEncryptionKeyUseCase::class)->generate($name);
        }

        return $keys->findActive();
    }

    /**
     * Generates the active key (when missing) and creates a backup via
     * the real create pipeline (the sync test queue runs the job
     * inline), returning the completed record.
     */
    protected function createCompletedBackup(): Backup
    {
        $this->activeEncryptionKey();
        $created = app(CreateBackupUseCase::class)->create();

        $entity = app(BackupRepositoryInterface::class)->find($created->toId());

        expect($entity)->not->toBeNull();

        return $entity;
    }
}
