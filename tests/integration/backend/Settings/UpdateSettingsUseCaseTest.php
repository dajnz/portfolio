<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\App\UseCases\UpdateSettingsUseCase;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Infra\Models\SettingModel;
use App\Features\Settings\Infra\Repositories\EloquentSettingRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use ValueError;

describe('UpdateSettingsUseCase', function (): void {
    beforeEach(function (): void {
        SettingModel::query()->create([
            'key' => 'items_per_page',
            'value' => '12',
            'type' => 'integer',
        ]);
        SettingModel::query()->create([
            'key' => 'site_maintenance_mode',
            'value' => '0',
            'type' => 'boolean',
        ]);
        SettingModel::query()->create([
            'key' => 'mail_password',
            'value' => '',
            'type' => 'string',
        ]);
    });

    it('persists a batch of settings', function (): void {
        app(UpdateSettingsUseCase::class)->update([
            'items_per_page' => 24,
            'site_maintenance_mode' => true,
        ]);

        expect(SettingModel::query()->find('items_per_page')->value)->toBe('24')
            ->and(SettingModel::query()->find('site_maintenance_mode')->value)->toBe('1');
    });

    it('invalidates the cache for every written key', function (): void {
        $repository = app(EloquentSettingRepository::class);
        $repository->find(SettingName::ItemsPerPage);
        $repository->find(SettingName::SiteMaintenanceMode);

        app(UpdateSettingsUseCase::class)->update([
            'items_per_page' => 24,
            'site_maintenance_mode' => true,
        ]);

        expect(Cache::has('settings:items_per_page'))->toBeFalse()
            ->and(Cache::has('settings:site_maintenance_mode'))->toBeFalse();
    });

    it('saves the batch atomically — a failing key rolls back the whole batch', function (): void {
        try {
            app(UpdateSettingsUseCase::class)->update([
                'site_maintenance_mode' => true,
                'items_per_page' => 0, // outside the name domain — Setting construction fails
            ]);
        } catch (InvalidArgumentException) {
            // expected
        }

        expect(SettingModel::query()->find('items_per_page')->value)->toBe('12')
            ->and(SettingModel::query()->find('site_maintenance_mode')->value)->toBe('0');
    });

    it('re-throws the failure from a failed batch', function (): void {
        app(UpdateSettingsUseCase::class)->update([
            'items_per_page' => new \stdClass(), // does not conform to the integer type
        ]);
    })->throws(InvalidArgumentException::class);

    it('stores mail_password encrypted', function (): void {
        app(UpdateSettingsUseCase::class)->update(['mail_password' => 'super-secret']);

        $stored = SettingModel::query()->find('mail_password')->value;

        expect($stored)->not->toBe('super-secret')
            ->and(Crypt::decryptString($stored))->toBe('super-secret');
    });

    it('keeps the existing mail_password when an empty one is submitted', function (): void {
        app(UpdateSettingsUseCase::class)->update(['mail_password' => 'super-secret']);
        $stored = SettingModel::query()->find('mail_password')->value;

        app(UpdateSettingsUseCase::class)->update(['mail_password' => '']);

        expect(SettingModel::query()->find('mail_password')->value)->toBe($stored)
            ->and(Crypt::decryptString($stored))->toBe('super-secret');
    });

    it('ignores an unknown key in the batch', function (): void {
        app(UpdateSettingsUseCase::class)->update([
            'bogus_key' => 'x',
        ]);
    })->throws(ValueError::class);
});
