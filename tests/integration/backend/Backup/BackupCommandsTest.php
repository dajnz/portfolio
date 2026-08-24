<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\App\UseCases\GenerateEncryptionKeyUseCase;
use App\Features\Backup\Infra\Jobs\CreateBackupJob;
use App\Features\Backup\Infra\Models\BackupEncryptionKeyModel;
use App\Features\Backup\Infra\Models\BackupModel;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Traits\InteractsWithBackupStorage;

uses(InteractsWithBackupStorage::class)->group('backup');

beforeEach(function (): void {
    $this->seedBackupEnvironment();
});

describe('Backup commands', function (): void {
    it('backup:create creates a pending record and dispatches the job', function (): void {
        Bus::fake([CreateBackupJob::class]);

        $this->artisan('backup:create')
            ->expectsOutputToContain('Backup job dispatched')
            ->assertExitCode(0);

        $record = BackupModel::query()->latest('id')->first();

        expect($record)->not->toBeNull()
            ->and($record->status)->toBe('pending');

        Bus::assertDispatched(CreateBackupJob::class);
    });

    it('backup:restore restores by filename round-trip', function (): void {
        app(GenerateEncryptionKeyUseCase::class)->generate('initial-key');
        $backup = app(\App\Features\Backup\App\UseCases\CreateBackupUseCase::class)->create();

        $this->artisan('backup:restore', ['filename' => $backup->toFilename()])
            ->expectsOutputToContain('restored successfully')
            ->assertExitCode(0);

        expect(BackupModel::query()->find($backup->toId())->status)->toBe('completed');
    });

    it('backup:restore fails for a missing filename', function (): void {
        $this->artisan('backup:restore', ['filename' => 'does-not-exist.zip'])
            ->expectsOutputToContain('No backup')
            ->assertExitCode(1);
    });

    it('backup:restore DR mode imports and restores a local archive', function (): void {
        $key = app(GenerateEncryptionKeyUseCase::class)->generate('initial-key');
        $backup = app(\App\Features\Backup\App\UseCases\CreateBackupUseCase::class)->create();

        $archiveBytes = Storage::disk('rustfs-backups')->get($backup->toFilename());
        $tempPath = tempnam(sys_get_temp_dir(), 'dr-cli-').'.zip';
        file_put_contents($tempPath, $archiveBytes);

        try {
            $this->artisan('backup:restore', [
                '--path' => $tempPath,
                '--key' => $key->toKeyValue(),
            ])->assertExitCode(0);
        } finally {
            @unlink($tempPath);
        }

        expect(BackupModel::query()->where('filename', 'like', 'backup-import-%')->exists())->toBeTrue()
            ->and(BackupEncryptionKeyModel::query()->where('is_active', false)->count())->toBe(1);
    });

    it('backup:restore DR mode rejects an invalid key', function (): void {
        $tempPath = tempnam(sys_get_temp_dir(), 'dr-cli-').'.zip';
        file_put_contents($tempPath, 'not-a-zip');

        try {
            $this->artisan('backup:restore', [
                '--path' => $tempPath,
                '--key' => 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
            ])->assertExitCode(1);
        } finally {
            @unlink($tempPath);
        }

        expect(BackupModel::query()->count())->toBe(0);
    });

    it('backup:prune removes the surplus oldest backups', function (): void {
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

        \App\Features\Settings\Infra\Models\SettingModel::query()
            ->where('key', 'backup_max_count')
            ->update(['value' => 1]);

        $this->artisan('backup:prune')
            ->expectsOutputToContain('Pruned 1 backup')
            ->assertExitCode(0);

        expect(BackupModel::query()->count())->toBe(1)
            ->and(BackupModel::query()->first()->filename)->toBe('backup-2026-02-01-020000-k1.zip');
    });

    it('backup:generate-key prints the raw value once and activates it', function (): void {
        $this->artisan('backup:generate-key', ['--name' => 'rotated-2026'])
            ->expectsOutputToContain('rotated-2026')
            ->expectsOutputToContain('never be shown again')
            ->assertExitCode(0);

        expect(BackupEncryptionKeyModel::query()->where('name', 'rotated-2026')->first()->is_active)->toBeTrue();
    });
});
