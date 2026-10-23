<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Backup;

use App\Features\Analytics\Infra\Models\AnalyticsEventModel;
use App\Features\Analytics\Infra\Models\AnalyticsReportModel;
use App\Features\Backup\App\Contracts\BackupArchiveInterface;
use App\Features\Backup\App\UseCases\RestoreBackupUseCase;
use App\Features\Backup\Infra\Archive\ZipBackupArchive;
use App\Features\Backup\Infra\Models\BackupModel;
use App\Features\Experience\Infra\Models\TimelineItemModel;
use App\Features\Experience\Infra\Models\TimelineItemTranslationModel;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Traits\InteractsWithBackupStorage;

uses(InteractsWithBackupStorage::class)->group('backup');

beforeEach(function (): void {
    $this->seedBackupEnvironment();

    // The shared fixture: a completed backup holding a project with an
    // image, a feedback row and an enabled feature flag.
    $this->project = ProjectModel::factory()->published()->create();
    Storage::disk('rustfs')->put('projects/abc123/preview.jpg', 'backed-up-image');
    FeedbackModel::factory()->create(['sender_name' => 'Jane Doe']);
    FeatureFlagModel::query()->updateOrCreate(
        ['key' => 'social_reactions'],
        ['enabled' => true, 'description' => ''],
    );

    $this->backup = $this->createCompletedBackup();
});

describe('RestoreWorkflowService — restore round-trip', function (): void {
    it('restores the archived content rows with their original IDs', function (): void {
        $this->project->forceDelete();
        FeedbackModel::query()->delete();

        app(RestoreBackupUseCase::class)->restore($this->backup);

        expect(ProjectModel::withTrashed()->find($this->project->id))->not->toBeNull()
            ->and(FeedbackModel::query()->where('sender_name', 'Jane Doe')->exists())->toBeTrue();
    });

    it('round-trips the external project URL and the achievements payload', function (): void {
        $item = TimelineItemModel::factory()->create([
            'started_at' => '2020-01-01',
            'ended_at' => '2021-01-01',
            'external_project_name' => 'Side Project',
            'external_project_url' => 'https://example.com/side-project',
        ]);
        TimelineItemTranslationModel::factory()->forLocale('en')->create([
            'item_id' => $item->id,
            'position' => 'Engineer',
            'company_name' => 'Acme',
            'company_country' => 'Germany',
            'short_description' => 'Built the **platform**.',
            'achievements' => [
                ['description' => 'Cut deploy time by [40%](https://example.com/metrics)'],
            ],
        ]);

        $backup = $this->createCompletedBackup();
        $item->forceDelete();

        app(RestoreBackupUseCase::class)->restore($backup);

        $restored = TimelineItemModel::query()->find($item->id);

        expect($restored)->not->toBeNull()
            ->and($restored->external_project_name)->toBe('Side Project')
            ->and($restored->external_project_url)->toBe('https://example.com/side-project')
            ->and($restored->translations()->where('locale', 'en')->first()->achievements)->toBe([
                ['description' => 'Cut deploy time by [40%](https://example.com/metrics)'],
            ]);
    });

    it('restores the settings by key and flushes the settings cache', function (): void {
        SettingModel::query()->where('key', 'items_per_page')->update(['value' => 99]);
        Cache::put('settings:items_per_page', 99, 60);

        app(RestoreBackupUseCase::class)->restore($this->backup);

        expect((string) SettingModel::query()->find('items_per_page')->value)->toBe('12')
            ->and(Cache::get('settings:items_per_page'))->toBeNull();
    });

    it('wipes the analytics stores on restore', function (): void {
        AnalyticsEventModel::query()->create([
            'event_type' => 'page_view',
            'session_id' => 'restore-test-session',
        ]);
        AnalyticsReportModel::query()->create([
            'metric' => 'page_views',
            'span' => 'all',
            'dimension' => null,
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'value' => 5,
        ]);
        DB::table('analytics_daily_metrics')->insert([
            'metric' => 'page_views',
            'date' => now()->toDateString(),
            'dimension' => null,
            'value' => 5,
            'samples' => 5,
        ]);

        app(RestoreBackupUseCase::class)->restore($this->backup);

        expect(AnalyticsEventModel::query()->count())->toBe(0)
            ->and(AnalyticsReportModel::query()->count())->toBe(0)
            ->and(DB::table('analytics_daily_metrics')->count())->toBe(0);
    });

    it('preserves the feature_flags table untouched', function (): void {
        app(RestoreBackupUseCase::class)->restore($this->backup);

        expect(FeatureFlagModel::query()->where('key', 'social_reactions')->first()->enabled)->toBeTrue();
    });

    it('mirrors the assets bucket to the manifest (extras deleted)', function (): void {
        Storage::disk('rustfs')->put('blog/stray/lead.jpg', 'stray-not-in-backup');

        app(RestoreBackupUseCase::class)->restore($this->backup);

        expect(Storage::disk('rustfs')->exists('projects/abc123/preview.jpg'))->toBeTrue()
            ->and(Storage::disk('rustfs')->get('projects/abc123/preview.jpg'))->toBe('backed-up-image')
            ->and(Storage::disk('rustfs')->exists('blog/stray/lead.jpg'))->toBeFalse();
    });

    it('re-completes the record and lifts the maintenance mode', function (): void {
        app(RestoreBackupUseCase::class)->restore($this->backup);

        $record = BackupModel::query()->find($this->backup->toId());

        expect($record->status)->toBe('completed')
            ->and($record->completed_at)->not->toBeNull()
            ->and($record->path)->toBe($record->filename)
            ->and((string) SettingModel::query()->find(SettingName::SiteMaintenanceMode->value)->value)->toBe('0');
    });

    it('restores a legacy plaintext backup via the BACKUP_ENCRYPTION_KEY fallback', function (): void {
        config()->set('backup.encryption_enabled', false);
        app()->instance(
            BackupArchiveInterface::class,
            new ZipBackupArchive(encryptionEnabled: false),
        );

        $legacy = $this->createCompletedBackup();
        config()->set('backup.encryption_key', 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=');

        expect($legacy->toEncryptionKeyId())->toBeNull();

        app(RestoreBackupUseCase::class)->restore($legacy);

        expect(BackupModel::query()->find($legacy->toId())->status)->toBe('completed')
            ->and(FeedbackModel::query()->where('sender_name', 'Jane Doe')->exists())->toBeTrue()
            ->and((string) SettingModel::query()->find(SettingName::SiteMaintenanceMode->value)->value)->toBe('0');
    });

    it('leaves the site in maintenance mode when the restore fails', function (): void {
        // Corrupt the archive — the restore's validate step fails.
        Storage::disk('rustfs-backups')->put(
            $this->backup->toFilename(),
            'this-is-not-a-zip',
        );

        try {
            app(RestoreBackupUseCase::class)->restore($this->backup);
            $this->fail('Expected the restore to fail on a corrupt archive.');
        } catch (\RuntimeException) {
            // expected
        }

        $record = BackupModel::query()->find($this->backup->toId());

        expect($record->status)->toBe('failed')
            ->and((string) SettingModel::query()->find(SettingName::SiteMaintenanceMode->value)->value)->toBe('1');
    });
});
