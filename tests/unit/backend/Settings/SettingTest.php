<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Settings;

use App\Features\Settings\Domain\Entities\Setting;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\Enums\SettingType;
use App\Features\Settings\Domain\ValueObjects\SettingValue;
use InvalidArgumentException;

describe('Setting', function (): void {
    it('accepts only names from the strictly defined set', function (): void {
        expect(Setting::isValidName('items_per_page'))->toBeTrue()
            ->and(Setting::isValidName('site_maintenance_mode'))->toBeTrue()
            ->and(Setting::isValidName('bogus_key'))->toBeFalse()
            ->and(Setting::isValidName(''))->toBeFalse();
    });

    it('rejects a value whose type does not match the name', function (): void {
        expect(fn () => new Setting(
            SettingName::ItemsPerPage,
            new SettingValue(SettingType::String, '12'),
        ))->toThrow(InvalidArgumentException::class);
    });

    it('holds a value that conforms to the name type', function (): void {
        $setting = new Setting(
            SettingName::ItemsPerPage,
            new SettingValue(SettingType::Integer, 12),
        );

        expect($setting->value())->toBe(12)
            ->and($setting->rawValue())->toBe('12');
    });

    it('reports its value type via the type-check methods', function (): void {
        $boolean = new Setting(SettingName::SiteMaintenanceMode, new SettingValue(SettingType::Boolean, false));
        $integer = new Setting(SettingName::ItemsPerPage, new SettingValue(SettingType::Integer, 12));
        $string = new Setting(SettingName::MailApiEndpoint, new SettingValue(SettingType::String, 'https://api.example.com'));
        $json = new Setting(SettingName::SupportedLocales, new SettingValue(SettingType::Json, ['en']));

        expect($boolean->isBoolean())->toBeTrue()
            ->and($boolean->isString())->toBeFalse()
            ->and($integer->isInteger())->toBeTrue()
            ->and($string->isString())->toBeTrue()
            ->and($json->isJson())->toBeTrue();
    });

    it('restricts value changes to the name type', function (): void {
        $setting = new Setting(SettingName::SiteMaintenanceMode, new SettingValue(SettingType::Boolean, false));

        $updated = $setting->updateValue(new SettingValue(SettingType::Boolean, true));

        expect($updated->value())->toBeTrue()
            ->and($setting->value())->toBeFalse();

        expect(fn () => $setting->updateValue(new SettingValue(SettingType::Integer, 1)))
            ->toThrow(InvalidArgumentException::class);
    });

    it('enforces the per-name value domains (A8 bounds)', function (): void {
        expect(fn () => new Setting(SettingName::ItemsPerPage, new SettingValue(SettingType::Integer, 0)))
            ->toThrow(InvalidArgumentException::class, "Setting 'items_per_page' must be at least 1.")
            ->and(fn () => new Setting(SettingName::BackupMaxCount, new SettingValue(SettingType::Integer, 0)))
            ->toThrow(InvalidArgumentException::class, "Setting 'backup_max_count' must be at least 1.")
            ->and(fn () => new Setting(SettingName::BackupUploadMaxSize, new SettingValue(SettingType::Integer, -5)))
            ->toThrow(InvalidArgumentException::class)
            ->and(fn () => new Setting(SettingName::TrashRetentionDays, new SettingValue(SettingType::Integer, -1)))
            ->toThrow(InvalidArgumentException::class, "Setting 'trash_retention_days' must be at least 0.")
            ->and(fn () => new Setting(SettingName::ItemsPerPage, new SettingValue(SettingType::Integer, 1)))
            ->not->toThrow(InvalidArgumentException::class)
            ->and(fn () => new Setting(SettingName::TrashRetentionDays, new SettingValue(SettingType::Integer, 0)))
            ->not->toThrow(InvalidArgumentException::class);
    });

    it('enforces the analytics report frequency domain', function (): void {
        expect(fn () => new Setting(
            SettingName::AnalyticsReportFrequency,
            new SettingValue(SettingType::String, 'hourly'),
        ))->toThrow(InvalidArgumentException::class, "Setting 'analytics_report_frequency' must be one of: daily, weekly, monthly.");

        foreach (['daily', 'weekly', 'monthly'] as $frequency) {
            expect(fn () => new Setting(
                SettingName::AnalyticsReportFrequency,
                new SettingValue(SettingType::String, $frequency),
            ))->not->toThrow(InvalidArgumentException::class);
        }
    });

    it('requires at least one supported locale', function (): void {
        expect(fn () => new Setting(SettingName::SupportedLocales, new SettingValue(SettingType::Json, [])))
            ->toThrow(InvalidArgumentException::class, "Setting 'supported_locales' must contain at least one locale.")
            ->and(fn () => new Setting(SettingName::SupportedLocales, new SettingValue(SettingType::Json, '[]')))
            ->toThrow(InvalidArgumentException::class, "Setting 'supported_locales' must contain at least one locale.")
            ->and(fn () => new Setting(SettingName::SupportedLocales, new SettingValue(SettingType::Json, ['en'])))
            ->not->toThrow(InvalidArgumentException::class);
    });
});
