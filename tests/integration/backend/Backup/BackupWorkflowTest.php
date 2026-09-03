<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Backup\App\Contracts\BackupArchiveInterface;
use App\Features\Backup\App\UseCases\CreateBackupUseCase;
use App\Features\Backup\Domain\Enums\BackupStatus;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Infra\Archive\ZipBackupArchive;
use App\Features\Backup\Infra\Models\BackupModel;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Traits\InteractsWithBackupStorage;

uses(InteractsWithBackupStorage::class)->group('backup');

beforeEach(function (): void {
    $this->seedBackupEnvironment();
});

/**
 * Seeds the fixture content of the create-pipeline tests: a project
 * with a screenshot image, a feedback row and two RustFS assets.
 */
function seedBackupContent(): void
{
    SettingModel::query()->where('key', 'items_per_page')->update(['value' => 24]);
    FeedbackModel::factory()->create(['sender_name' => 'Jane Doe']);
    $project = ProjectModel::factory()->published()->create();
    ProjectScreenshotModel::factory()->create([
        'project_id' => $project->id,
        'image_path' => 'projects/abc123/preview.jpg',
    ]);
    Storage::disk('rustfs')->put('projects/abc123/preview.jpg', 'fake-image-bytes');
    Storage::disk('rustfs')->put('about/photo.jpg', 'portrait-bytes');
}

describe('BackupWorkflowService — create pipeline', function (): void {
    it('completes the record and uploads the archive under the record name', function (): void {
        seedBackupContent();
        $key = $this->activeEncryptionKey();

        $created = app(CreateBackupUseCase::class)->create();
        $created = app(BackupRepositoryInterface::class)->find($created->toId());

        expect($created->toStatus())->toBe(BackupStatus::Completed);

        $record = BackupModel::query()->find($created->toId());
        $files = Storage::disk('rustfs-backups')->allFiles();

        expect($record->filename)->toContain('-k'.$key->toId().'--'.$key->toName().'.zip')
            ->and($record->path)->toBe($record->filename)
            ->and($files)->toHaveCount(1)
            ->and($files[0])->toBe($record->filename)
            ->and(Storage::disk('rustfs-backups')->size($files[0]))->toBe($record->size_bytes);
    });

    it('records the per-table row counts in the manifest', function (): void {
        seedBackupContent();
        $key = $this->activeEncryptionKey();
        $backup = $this->createCompletedBackup();

        $manifest = app(BackupArchiveInterface::class)
            ->readManifest(Storage::disk('rustfs-backups')->path($backup->toFilename()), $key->toKeyValue());

        expect($manifest->recordCount('settings'))->toBe(16)
            ->and($manifest->recordCount('feedback'))->toBe(1)
            ->and($manifest->recordCount('projects'))->toBe(1)
            ->and($manifest->recordCount('project_screenshots'))->toBe(1);
    });

    it('records the staged assets with their byte sizes in the manifest', function (): void {
        seedBackupContent();
        $key = $this->activeEncryptionKey();
        $backup = $this->createCompletedBackup();

        $manifest = app(BackupArchiveInterface::class)
            ->readManifest(Storage::disk('rustfs-backups')->path($backup->toFilename()), $key->toKeyValue());

        $expectedBytes = Storage::disk('rustfs')->size('projects/abc123/preview.jpg')
            + Storage::disk('rustfs')->size('about/photo.jpg');

        expect($manifest->toAssetsCount())->toBe(2)
            ->and($manifest->toAssetsSizeBytes())->toBe($expectedBytes)
            ->and($manifest->assetPaths())->toContain('projects/abc123/preview.jpg')
            ->and($manifest->assetPaths())->toContain('about/photo.jpg');
    });

    it('fails the record when no active key exists and encryption is enabled', function (): void {
        $useCase = app(CreateBackupUseCase::class);

        try {
            $useCase->create();
            $this->fail('Expected the job to fail without an active key.');
        } catch (\RuntimeException) {
            // expected
        }

        $record = BackupModel::query()->latest('id')->first();

        expect($record->status)->toBe('failed')
            ->and($record->completed_at)->not->toBeNull()
            ->and(Storage::disk('rustfs-backups')->allFiles())->toBeEmpty();
    });

    it('prunes the oldest backups beyond backup_max_count after a successful backup', function (): void {
        SettingModel::query()->where('key', 'backup_max_count')->update(['value' => 1]);

        // A pre-existing older backup (distinct filename — the B18 name
        // has second precision, a same-second second backup would
        // collide with the first's object key).
        BackupModel::factory()->create([
            'filename' => 'backup-2026-01-01-020000-k1.zip',
            'path' => 'backup-2026-01-01-020000-k1.zip',
            'created_at' => '2026-01-01 02:00:00',
            'completed_at' => '2026-01-01 02:00:05',
            'encryption_key_id' => null,
        ]);
        Storage::disk('rustfs-backups')->put('backup-2026-01-01-020000-k1.zip', 'old-archive');

        $created = $this->createCompletedBackup();

        $repository = app(BackupRepositoryInterface::class);

        expect($repository->countActive())->toBe(1)
            ->and($repository->findByFilename('backup-2026-01-01-020000-k1.zip'))->toBeNull()
            ->and($repository->find($created->toId()))->not->toBeNull();

        $files = Storage::disk('rustfs-backups')->allFiles();

        expect($files)->toHaveCount(1)
            ->and($files[0])->toBe($created->toFilename());
    });

    it('runs a plaintext archive when encryption is disabled', function (): void {
        config()->set('backup.encryption_enabled', false);
        app()->instance(
            BackupArchiveInterface::class,
            new ZipBackupArchive(encryptionEnabled: false),
        );

        FeedbackModel::factory()->create(['sender_name' => 'Jane']);

        $created = $this->createCompletedBackup();

        expect($created->toStatus())->toBe(BackupStatus::Completed)
            ->and($created->toFilename())->not->toContain('-k');

        $files = Storage::disk('rustfs-backups')->allFiles();
        $key = new \App\Features\Backup\Domain\Entities\EncryptionKey(
            id: 0,
            name: 'any',
            keyValue: 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
            isActive: false,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $manifest = app(BackupArchiveInterface::class)
            ->readManifest(Storage::disk('rustfs-backups')->path($files[0]), $key->toKeyValue());

        expect($manifest->recordCount('feedback'))->toBe(1);
    });
});
