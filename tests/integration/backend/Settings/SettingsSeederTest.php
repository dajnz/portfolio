<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Support\Facades\Crypt;

describe('SettingsSeeder', function (): void {
    it('inserts exactly the predefined settings keys with their defaults', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $expected = [
            'site_maintenance_mode',
            'supported_locales',
            'items_per_page',
            'projects_per_page',
            'articles_per_page',
            'experience_items_per_page',
            'analytics_email_enabled',
            'analytics_email_recipient',
            'email_on_feedback',
            'mail_api_endpoint',
            'mail_api_token',
            'mail_from_address',
            'mail_from_name',
            'backup_max_count',
            'backup_upload_max_size',
            'trash_retention_days',
        ];

        $keys = SettingModel::query()->orderBy('key')->pluck('key')->all();

        sort($expected);

        expect($keys)->toBe($expected);

        $values = SettingModel::query()->pluck('value', 'key');

        expect($values['site_maintenance_mode'])->toBe('0')
            ->and($values['supported_locales'])->toBe(json_encode(config('portfolio.supported_locales')))
            ->and($values['items_per_page'])->toBe((string) config('portfolio.items_per_page'))
            ->and($values['projects_per_page'])->toBe((string) config('portfolio.projects_per_page'))
            ->and($values['articles_per_page'])->toBe((string) config('portfolio.articles_per_page'))
            ->and($values['experience_items_per_page'])->toBe((string) config('portfolio.experience_items_per_page'))
            ->and($values['analytics_email_enabled'])->toBe('0')
            ->and($values['email_on_feedback'])->toBe('1')
            ->and($values['backup_max_count'])->toBe((string) config('backup.max_count'))
            ->and($values['backup_upload_max_size'])->toBe('102400')
            ->and($values['trash_retention_days'])->toBe('30');
    });

    it('matches the SettingName enum exactly (strict name-to-type mapping)', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $seededKeys = SettingModel::query()->orderBy('key')->pluck('key')->all();
        $enumKeys = array_column(SettingName::cases(), 'value');
        sort($seededKeys);
        sort($enumKeys);

        expect($seededKeys)->toBe($enumKeys);

        foreach (SettingName::cases() as $name) {
            expect(SettingModel::query()->find($name->value)->type)->toBe($name->type()->value);
        }
    });

    it('seeds mail_api_token encrypted', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $value = SettingModel::query()->find('mail_api_token')->value;

        expect($value)->toBeString();

        if ($value !== '') {
            expect(Crypt::decryptString($value))->not->toBe('');
        }
    });

    it('is idempotent and refreshes values on re-seed', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        SettingModel::query()->whereKey('items_per_page')->update(['value' => '99']);

        $this->seed(\Database\Seeders\SettingsSeeder::class);

        expect(SettingModel::query()->find('items_per_page')->value)
            ->toBe((string) config('portfolio.items_per_page'));
    });
});

describe('FeatureFlagSeeder', function (): void {
    it('inserts the three initial flags with enabled = false', function (): void {
        $this->seed(\Database\Seeders\FeatureFlagSeeder::class);

        $keys = FeatureFlagModel::query()->orderBy('key')->pluck('key')->all();
        sort($keys);

        expect($keys)->toBe(['console_greeting', 'github_oauth', 'social_reactions'])
            ->and(FeatureFlagModel::query()->where('enabled', false)->count())->toBe(3);
    });

    it('is idempotent', function (): void {
        $this->seed(\Database\Seeders\FeatureFlagSeeder::class);
        $this->seed(\Database\Seeders\FeatureFlagSeeder::class);

        expect(FeatureFlagModel::query()->count())->toBe(3);
    });
});

describe('DatabaseSeeder', function (): void {
    it('composes the settings and feature flag seeders without touching admins', function (): void {
        $this->seed();

        expect(SettingModel::query()->count())->toBe(16)
            ->and(FeatureFlagModel::query()->count())->toBe(3);
    });
});
