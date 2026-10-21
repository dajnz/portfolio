<?php

declare(strict_types=1);

namespace App\Features\Settings\App\UseCases;

use App\Features\Shared\App\Contracts\MaintenanceModeInterface;

/**
 * The ops escape hatch for a failed restore: lifts the setting-based site
 * maintenance mode without touching the admin UI.
 */
final class DisableSiteMaintenanceUseCase
{
    public function __construct(
        private readonly MaintenanceModeInterface $maintenance,
    ) {
    }

    public function disable(): void
    {
        $this->maintenance->disable();
    }
}
