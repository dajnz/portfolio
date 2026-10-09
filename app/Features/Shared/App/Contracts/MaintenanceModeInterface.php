<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The setting-based maintenance mode toggle: the restore workflow puts the
 * site into maintenance before the data plane runs and lifts it afterwards.
 * An abstraction over the Settings feature's `site_maintenance_mode` write
 * so cross-feature consumers never touch the Settings feature directly; the
 * framework implementation lives on the Shared Infra layer.
 */
interface MaintenanceModeInterface
{
    public function enable(): void;

    public function disable(): void;
}
