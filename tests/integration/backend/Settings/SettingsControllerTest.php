<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

describe('SettingsController', function (): void {
    beforeEach(function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    });

    it('renders the settings page with a typed key => value map', function (): void {
        $this->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Admin/Pages/SettingsPage')
                ->where('settings.items_per_page', 12)
                ->where('settings.site_maintenance_mode', false)
                ->where('settings.supported_locales', ['en', 'ru'])
                ->where('settings.email_on_feedback', true));
    });

    it('masks the mail_password in the page props', function (): void {
        app(\App\Features\Settings\App\UseCases\UpdateSettingsUseCase::class)
            ->update(['mail_password' => 'super-secret']);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.mail_password', ''));
    });

    it('keeps the stored mail_password when the Mail tab is saved without a new one', function (): void {
        app(\App\Features\Settings\App\UseCases\UpdateSettingsUseCase::class)
            ->update(['mail_password' => 'super-secret']);

        $stored = \App\Features\Settings\Infra\Models\SettingModel::query()->find('mail_password')->value;

        $this->put('/admin/settings', [
            'settings' => ['mail_password' => '', 'mail_host' => 'smtp.example.com'],
        ])->assertRedirect();

        expect(\App\Features\Settings\Infra\Models\SettingModel::query()->find('mail_password')->value)->toBe($stored);
    });

    it('persists a valid tab payload and redirects back', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['items_per_page' => 24, 'site_maintenance_mode' => true],
        ])->assertRedirect();

        expect(SettingModel::query()->find('items_per_page')->value)->toBe('24')
            ->and(SettingModel::query()->find('site_maintenance_mode')->value)->toBe('1');
    });

    it('accepts boolean settings as true/false/1/0 and integer settings as numbers', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['site_maintenance_mode' => 'false', 'items_per_page' => '30'],
        ])->assertRedirect();

        expect(SettingModel::query()->find('site_maintenance_mode')->value)->toBe('0')
            ->and(SettingModel::query()->find('items_per_page')->value)->toBe('30');
    });

    it('accepts json settings as an array or a valid JSON string', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['supported_locales' => ['en', 'ru']],
        ])->assertRedirect();

        $this->put('/admin/settings', [
            'settings' => ['supported_locales' => '["en"]'],
        ])->assertRedirect();

        expect(SettingModel::query()->find('supported_locales')->value)->toBe('["en"]');
    });

    it('rejects a type mismatch with the per-key message', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['items_per_page' => 'abc'],
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'settings.items_per_page' => "Invalid value type for 'items_per_page'.",
            ]);
    });

    it('rejects a boolean type mismatch', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['site_maintenance_mode' => 'yes'],
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'settings.site_maintenance_mode' => "Invalid value type for 'site_maintenance_mode'.",
            ]);
    });

    it('rejects an invalid JSON value', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['supported_locales' => 'not-json'],
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'settings.supported_locales' => "Invalid value type for 'supported_locales'.",
            ]);
    });

    it('rejects a non-integer value for an integer setting', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['items_per_page' => 12.5],
        ])->assertRedirect()
            ->assertSessionHasErrors('settings.items_per_page');
    });

    it('rejects unknown keys (exists:settings,key)', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['bogus_key' => 'x'],
        ])->assertRedirect()
            ->assertSessionHasErrors('settings.bogus_key');
    });

    it('applies numeric bounds per A8 — per-page and backup settings need min:1', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['items_per_page' => 0],
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'settings.items_per_page' => "Setting 'items_per_page' must be at least 1.",
            ]);

        $this->put('/admin/settings', [
            'settings' => ['backup_max_count' => 0],
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'settings.backup_max_count' => "Setting 'backup_max_count' must be at least 1.",
            ]);

        $this->put('/admin/settings', [
            'settings' => ['backup_upload_max_size' => -5],
        ])->assertRedirect()
            ->assertSessionHasErrors('settings.backup_upload_max_size');

        $this->put('/admin/settings', [
            'settings' => ['backup_upload_max_size' => 1],
        ])->assertRedirect();
    });

    it('allows trash_retention_days to be 0 (pruning disabled)', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['trash_retention_days' => 0],
        ])->assertRedirect();
    });

    it('rejects negative trash_retention_days', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['trash_retention_days' => -1],
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'settings.trash_retention_days' => "Setting 'trash_retention_days' must be at least 0.",
            ]);
    });

    it('enforces the analytics_report_frequency domain', function (): void {
        $this->put('/admin/settings', [
            'settings' => ['analytics_report_frequency' => 'hourly'],
        ])->assertRedirect()
            ->assertSessionHasErrors([
                'settings.analytics_report_frequency' => "Setting 'analytics_report_frequency' must be one of: daily, weekly, monthly.",
            ]);

        $this->put('/admin/settings', [
            'settings' => ['analytics_report_frequency' => 'weekly'],
        ])->assertRedirect();
    });

    it('requires the settings payload', function (): void {
        $this->put('/admin/settings', [])->assertRedirect()
            ->assertSessionHasErrors('settings');
    });

    it('toggles feature flags and returns them in the response', function (): void {
        $this->seed(\Database\Seeders\FeatureFlagSeeder::class);

        $this->patchJson('/admin/settings/feature-flags', [
            'flags' => ['github_oauth' => true, 'social_reactions' => false],
        ])->assertOk()
            ->assertJson([
                'data' => [
                    'flags' => ['github_oauth' => true, 'social_reactions' => false],
                ],
            ]);

        expect(FeatureFlagModel::query()->find('github_oauth')->enabled)->toBeTrue()
            ->and(FeatureFlagModel::query()->find('social_reactions')->enabled)->toBeFalse()
            ->and(FeatureFlagModel::query()->find('console_greeting')->enabled)->toBeFalse();
    });

    it('invalidates the flag cache when flags are toggled', function (): void {
        $this->seed(\Database\Seeders\FeatureFlagSeeder::class);

        $repository = app(\App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface::class);

        $repository->isEnabled(FeatureFlagName::GithubOauth);
        expect(Cache::has('feature_flag:github_oauth'))->toBeTrue();

        $this->patchJson('/admin/settings/feature-flags', [
            'flags' => ['github_oauth' => true],
        ])->assertOk();

        expect(Cache::has('feature_flag:github_oauth'))->toBeFalse()
            ->and($repository->isEnabled(FeatureFlagName::GithubOauth))->toBeTrue();
    });

    it('rejects a non-boolean flag value', function (): void {
        $this->patch('/admin/settings/feature-flags', [
            'flags' => ['github_oauth' => 'yes'],
        ])->assertRedirect()
            ->assertSessionHasErrors('flags.github_oauth');
    });

    it('rejects an unknown flag key', function (): void {
        $this->patch('/admin/settings/feature-flags', [
            'flags' => ['bogus_flag' => true],
        ])->assertRedirect()
            ->assertSessionHasErrors('flags.bogus_flag');
    });

    it('rejects a missing flags payload', function (): void {
        $this->patch('/admin/settings/feature-flags', [])->assertRedirect()
            ->assertSessionHasErrors('flags');
    });
});
