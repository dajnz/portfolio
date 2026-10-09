<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\MaintenanceModeInterface;

/**
 * The setting-based maintenance toggle: writes the `site_maintenance_mode`
 * setting through the Settings feature's repository (cache-invalidating). Used
 * by the restore workflow; the ops-level `admin:reset-maintenance` command and
 * the Settings page toggle the same setting directly.
 */
final class LaravelMaintenanceMode implements MaintenanceModeInterface
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    public function enable(): void
    {
        $this->settings->saveValue(SettingName::SiteMaintenanceMode, true);
    }

    public function disable(): void
    {
        $this->settings->saveValue(SettingName::SiteMaintenanceMode, false);
    }
}
