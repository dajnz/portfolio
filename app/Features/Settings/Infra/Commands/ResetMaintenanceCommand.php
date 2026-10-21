<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Commands;

use App\Features\Settings\App\UseCases\DisableSiteMaintenanceUseCase;
use Illuminate\Console\Command;

/**
 * `admin:reset-maintenance` — lifts the site maintenance mode without
 * touching the admin UI (ops-level escape hatch).
 */
final class ResetMaintenanceCommand extends Command
{
    protected $signature = 'admin:reset-maintenance';

    protected $description = 'Disable the site maintenance mode (site_maintenance_mode setting)';

    public function handle(DisableSiteMaintenanceUseCase $maintenance): int
    {
        $maintenance->disable();

        $this->info('Maintenance mode disabled.');

        return self::SUCCESS;
    }
}
