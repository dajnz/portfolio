<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

/**
 * Opens the Horizon gate. Access control is delegated to the middleware
 * chain configured in `config/horizon.php` (`auth` + `auth.session_version`
 * + `admin`) — the built-in default gate would 403 everywhere outside
 * `local` even for a fully authenticated admin.
 */
final class HorizonGateProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (class_exists(Horizon::class)) {
            Horizon::auth(fn (Request $request): bool => true);
        }
    }
}
