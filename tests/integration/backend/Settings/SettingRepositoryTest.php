<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Settings\Infra\Models\SettingModel;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

describe('EloquentSettingRepository', function (): void {
    beforeEach(function (): void {
        SettingModel::insert([
            ['key' => 'items_per_page', 'value' => '12', 'type' => 'integer'],
            ['key' => 'site_maintenance_mode', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'supported_locales', 'value' => '["en","ru"]', 'type' => 'json'],
            ['key' => 'mail_api_endpoint', 'value' => 'https://api.example.com/v1/mail', 'type' => 'string'],
        ]);
    });

    it('returns settings typed by their name', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        expect($repository->find(SettingName::ItemsPerPage)->value())->toBe(12)
            ->and($repository->find(SettingName::SiteMaintenanceMode)->value())->toBeFalse()
            ->and($repository->find(SettingName::SupportedLocales)->value())->toBe(['en', 'ru'])
            ->and($repository->find(SettingName::MailApiEndpoint)->value())->toBe('https://api.example.com/v1/mail');
    });

    it('falls back to the caller-provided default when the row is missing', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        expect($repository->value(SettingName::ProjectsPerPage, 99))->toBe(99)
            ->and($repository->find(SettingName::ProjectsPerPage))->toBeNull();
    });

    it('caches values and serves them from the cache', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        $repository->find(SettingName::ItemsPerPage);

        expect(Cache::has('setting:v2:items_per_page'))->toBeTrue()
            ->and(Cache::get('setting:v2:items_per_page'))->toBe(['value' => 12]);
    });

    it('rebuilds the entity from the cached value on read', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        $repository->find(SettingName::ItemsPerPage);
        SettingModel::query()->whereKey('items_per_page')->update(['value' => '99']);

        // A raw DB write behind the cache's back must not be observed.
        expect($repository->find(SettingName::ItemsPerPage)->value())->toBe(12);
    });

    it('does not cache misses, so the config fallback never goes stale', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        $repository->find(SettingName::ProjectsPerPage);

        expect(Cache::has('setting:v2:projects_per_page'))->toBeFalse();
    });

    it('persists typed values and invalidates the cache on write', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        $repository->find(SettingName::ItemsPerPage);
        expect(Cache::has('setting:v2:items_per_page'))->toBeTrue();

        $repository->saveValue(SettingName::ItemsPerPage, 7);

        expect(Cache::has('setting:v2:items_per_page'))->toBeFalse()
            ->and($repository->value(SettingName::ItemsPerPage))->toBe(7)
            ->and(SettingModel::query()->find('items_per_page')->value)->toBe('7');
    });

    it('rejects a value that does not conform to the name type', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        expect(fn () => $repository->saveValue(SettingName::ItemsPerPage, 'abc'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects a value outside the name domain', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        expect(fn () => $repository->saveValue(SettingName::ItemsPerPage, 0))
            ->toThrow(InvalidArgumentException::class, "Setting 'items_per_page' must be at least 1.");
    });

    it('creates new rows from a name and value', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        $repository->saveValue(SettingName::TrashRetentionDays, 30);

        expect(SettingModel::query()->find('trash_retention_days')->value)->toBe('30')
            ->and(SettingModel::query()->find('trash_retention_days')->type)->toBe('integer');
    });

    it('updates an existing row in place', function (): void {
        $repository = app(SettingRepositoryInterface::class);

        $repository->saveValue(SettingName::SiteMaintenanceMode, true);

        expect(SettingModel::query()->count())->toBe(4)
            ->and(SettingModel::query()->find('site_maintenance_mode')->value)->toBe('1');
    });

    it('lists settings ordered by key and skips rows outside the defined names', function (): void {
        SettingModel::query()->create(['key' => 'bogus_key', 'value' => 'x', 'type' => 'string']);

        $settings = app(SettingRepositoryInterface::class)->findAll();

        expect(array_map(
            static fn ($setting): string => $setting->toName(),
            $settings,
        ))->toBe(['items_per_page', 'mail_api_endpoint', 'site_maintenance_mode', 'supported_locales']);
    });
});
