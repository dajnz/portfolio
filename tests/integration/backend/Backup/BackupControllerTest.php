<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\App\UseCases\GenerateEncryptionKeyUseCase;
use App\Features\Backup\Infra\Jobs\CreateBackupJob;
use App\Features\Backup\Infra\Jobs\RestoreBackupJob;
use App\Features\Backup\Infra\Models\BackupEncryptionKeyModel;
use App\Features\Backup\Infra\Models\BackupModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;
use Tests\Support\Traits\InteractsWithBackupStorage;

uses(InteractsWithAdmin::class, InteractsWithBackupStorage::class)->group('backup');

beforeEach(function (): void {
    $this->seedBackupEnvironment();
    $this->loginAsAdmin($this->createAdmin());
});

describe('BackupController', function (): void {
    it('renders the backup list page with the committed props', function (): void {
        $backup = BackupModel::factory()->create(['filename' => 'backup-2026-08-01-020000-k1.zip']);

        $this->get('/admin/backups')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Backup/Admin/Pages/BackupListPage')
                ->where('backups.0.id', $backup->id)
                ->where('backups.0.filename', 'backup-2026-08-01-020000-k1.zip')
                ->where('backups.0.status', 'completed')
                ->where('meta.total', 1)
                ->where('meta.per_page', 12)
                ->where('meta.current_page', 1)
                ->where('meta.last_page', 1)
                ->where('encryption_key_prompt', true));
    });

    it('creates a pending record and dispatches the job, then redirects with a started toast', function (): void {
        Bus::fake([CreateBackupJob::class]);

        $this->post('/admin/backups')
            ->assertRedirect()
            ->assertSessionHas('notification', ['type' => 'success', 'message' => __('admin.toasts.backup_creation_started')]);

        $record = BackupModel::query()->latest('id')->first();

        expect($record)->not->toBeNull()
            ->and($record->status)->toBe('pending');

        Bus::assertDispatched(CreateBackupJob::class, fn (CreateBackupJob $job): bool => $job->backupId === $record->id);
    });

    it('deletes the record and hard-deletes the archive', function (): void {
        $key = app(GenerateEncryptionKeyUseCase::class)->generate('initial-key');
        $backup = BackupModel::factory()->create([
            'filename' => 'backup-2026-08-01-020000-k'.$key->toId().'.zip',
            'path' => 'backup-2026-08-01-020000-k'.$key->toId().'.zip',
            'encryption_key_id' => $key->toId(),
        ]);
        Storage::disk('rustfs-backups')->put($backup->path, 'zip-bytes');

        $this->delete("/admin/backups/{$backup->id}")
            ->assertRedirect()
            ->assertSessionHas('notification', ['type' => 'success', 'message' => __('admin.toasts.backup_deleted')]);

        expect(BackupModel::withTrashed()->find($backup->id)->deleted_at)->not->toBeNull()
            ->and(Storage::disk('rustfs-backups')->exists($backup->path))->toBeFalse();
    });

    it('rejects deleting a processing backup', function (): void {
        $backup = BackupModel::factory()->create(['status' => 'processing']);

        $this->delete("/admin/backups/{$backup->id}")
            ->assertRedirect()
            ->assertSessionHas('notification', fn ($n) => $n['type'] === 'error');

        expect(BackupModel::query()->find($backup->id))->not->toBeNull();
    });

    it('requires the current password for the restore submit', function (): void {
        $backup = BackupModel::factory()->create();

        $this->post('/admin/backups/restore', [
            'backup_id' => $backup->id,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->post('/admin/backups/restore', [
            'backup_id' => $backup->id,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['password' => __('auth.current_password_incorrect')]);

        $this->post('/admin/backups/restore', [
            'backup_id' => $backup->id,
        ])->assertSessionHasErrors('password');
    });

    it('dispatches the restore job for a completed backup and redirects', function (): void {
        Bus::fake([RestoreBackupJob::class]);
        $backup = BackupModel::factory()->create();

        $this->post('/admin/backups/restore', [
            'backup_id' => $backup->id,
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect()
            ->assertSessionHas('notification', ['type' => 'success', 'message' => __('admin.toasts.backup_restore_started')]);

        Bus::assertDispatched(RestoreBackupJob::class, fn (RestoreBackupJob $job): bool => $job->backupId === $backup->id);
    });

    it('rejects the restore submit for a non-completed backup', function (): void {
        $backup = BackupModel::factory()->create(['status' => 'pending']);

        $this->post('/admin/backups/restore', [
            'backup_id' => $backup->id,
            'password' => self::ADMIN_PASSWORD,
        ])->assertRedirect()
            ->assertSessionHas('notification', fn ($n) => $n['type'] === 'error');
    });

    it('streams the archive download for a completed backup', function (): void {
        $backup = BackupModel::factory()->create(['path' => 'backup-2026-08-01-020000-k1.zip']);
        Storage::disk('rustfs-backups')->put($backup->path, 'encrypted-zip-bytes');

        $this->get("/admin/backups/{$backup->id}/download")
            ->assertOk()
            ->assertDownload($backup->filename)
            ->assertHeader('Content-Type', 'application/zip');
    });

    it('404s the download for a pending or deleted backup', function (): void {
        $pending = BackupModel::factory()->create(['status' => 'pending']);

        $this->get("/admin/backups/{$pending->id}/download")->assertNotFound();

        $deleted = BackupModel::factory()->create();
        $deleted->delete();

        $this->get("/admin/backups/{$deleted->id}/download")->assertNotFound();
    });

    it('returns the status envelope for polling', function (): void {
        $completed = BackupModel::factory()->create();

        $this->getJson("/admin/backups/{$completed->id}/status")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $completed->id,
                    'status' => 'completed',
                    'progress' => null,
                    'error_message' => null,
                ],
            ])
            ->assertJsonPath('data.completed_at', $completed->completed_at->format('c'));

        $failed = BackupModel::factory()->create(['status' => 'failed', 'completed_at' => now()]);
        Cache::put("backup:error:{$failed->id}", 'boom', 3600);

        $this->getJson("/admin/backups/{$failed->id}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error_message', 'boom');

        $this->getJson('/admin/backups/999999/status')->assertNotFound();
    });

    it('imports and restores an uploaded DR archive with a freshly imported disaster key', function (): void {
        Bus::fake([RestoreBackupJob::class]);

        $key = app(GenerateEncryptionKeyUseCase::class)->generate('initial-key');
        $backup = app(\App\Features\Backup\App\UseCases\CreateBackupUseCase::class)->create();

        $archiveBytes = Storage::disk('rustfs-backups')->get($backup->toFilename());
        $tempPath = tempnam(sys_get_temp_dir(), 'dr-upload-').'.zip';
        file_put_contents($tempPath, $archiveBytes);

        // The fresh-instance scenario: no stored key matches the
        // archive's — drop the source key (and its backup record) first.
        BackupModel::query()->whereKey($backup->toId())->forceDelete();
        BackupEncryptionKeyModel::query()->whereKey($key->toId())->delete();

        try {
            $this->post('/admin/backups/upload', [
                'file' => new UploadedFile($tempPath, $backup->toFilename(), 'application/zip', null, true),
                'key' => $key->toKeyValue(),
                'password' => self::ADMIN_PASSWORD,
            ])->assertRedirect('/admin/backups')
                ->assertSessionHas('notification', ['type' => 'success', 'message' => __('admin.toasts.backup_restore_started')]);
        } finally {
            @unlink($tempPath);
        }

        $imported = BackupModel::query()->latest('id')->first();

        expect($imported->filename)->toContain('backup-import-')
            ->and($imported->status)->toBe('completed');

        $importedKey = BackupEncryptionKeyModel::query()->whereKey($imported->encryption_key_id)->first();

        expect($importedKey)->not->toBeNull()
            ->and($importedKey->name)->toMatch('/^disaster-key-[0-9a-f]{8}$/')
            ->and($importedKey->is_active)->toBeFalse()
            ->and($imported->filename)->toContain('-k'.$importedKey->id.'--'.$importedKey->name.'.zip');

        Bus::assertDispatched(RestoreBackupJob::class, fn (RestoreBackupJob $job): bool => $job->backupId === $imported->id);
    });

    it('reuses the matching stored key when the DR archive key already exists', function (): void {
        Bus::fake([RestoreBackupJob::class]);

        $key = app(GenerateEncryptionKeyUseCase::class)->generate('initial-key');
        $backup = app(\App\Features\Backup\App\UseCases\CreateBackupUseCase::class)->create();

        $archiveBytes = Storage::disk('rustfs-backups')->get($backup->toFilename());
        $tempPath = tempnam(sys_get_temp_dir(), 'dr-upload-').'.zip';
        file_put_contents($tempPath, $archiveBytes);

        try {
            $this->post('/admin/backups/upload', [
                'file' => new UploadedFile($tempPath, $backup->toFilename(), 'application/zip', null, true),
                'key' => $key->toKeyValue(),
                'password' => self::ADMIN_PASSWORD,
            ])->assertRedirect('/admin/backups');
        } finally {
            @unlink($tempPath);
        }

        $imported = BackupModel::query()->latest('id')->first();

        // No duplicate key row — the imported record references the
        // existing key (its `-k{id}--{name}` filename suffix included).
        expect(BackupEncryptionKeyModel::query()->count())->toBe(1)
            ->and($imported->encryption_key_id)->toBe($key->toId())
            ->and($imported->filename)->toContain('-k'.$key->toId().'--'.$key->toName().'.zip');

        Bus::assertDispatched(RestoreBackupJob::class, fn (RestoreBackupJob $job): bool => $job->backupId === $imported->id);
    });

    it('rejects a DR upload with a mismatched key', function (): void {
        $key = app(GenerateEncryptionKeyUseCase::class)->generate('initial-key');
        $backup = app(\App\Features\Backup\App\UseCases\CreateBackupUseCase::class)->create();

        $archiveBytes = Storage::disk('rustfs-backups')->get($backup->toFilename());
        $tempPath = tempnam(sys_get_temp_dir(), 'dr-upload-').'.zip';
        file_put_contents($tempPath, $archiveBytes);

        try {
            $this->post('/admin/backups/upload', [
                'file' => new UploadedFile($tempPath, $backup->toFilename(), 'application/zip', null, true),
                'key' => 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
                'password' => self::ADMIN_PASSWORD,
            ])->assertSessionHasErrors('file');
        } finally {
            @unlink($tempPath);
        }

        expect(BackupModel::query()->where('filename', 'like', 'backup-import-%')->exists())->toBeFalse();
    });
});
