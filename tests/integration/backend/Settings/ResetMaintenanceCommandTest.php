<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Infra\Repositories\EloquentSettingRepository;
use Illuminate\Support\Facades\Cache;

describe('ResetMaintenanceCommand', function (): void {
    it('lifts the site maintenance mode and invalidates the cache', function (): void {
        $repository = app(EloquentSettingRepository::class);
        $repository->saveValue(SettingName::SiteMaintenanceMode, true);

        $repository->find(SettingName::SiteMaintenanceMode);
        expect(Cache::has('settings:site_maintenance_mode'))->toBeTrue();

        $this->artisan('admin:reset-maintenance')
            ->expectsOutputToContain('Maintenance mode disabled.')
            ->assertSuccessful();

        expect(Cache::has('settings:site_maintenance_mode'))->toBeFalse()
            ->and($repository->value(SettingName::SiteMaintenanceMode))->toBeFalse();
    });
});
