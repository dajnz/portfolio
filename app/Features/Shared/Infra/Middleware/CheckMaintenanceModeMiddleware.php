<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * `maintenance` middleware alias — the setting-based maintenance layer: when
 * the `site_maintenance_mode` setting is enabled, public web requests are
 * short-circuited to the 503 Blade view while `/admin/*` and static assets
 * (Vite `build/`, RustFS `assets/`, `storage/`) keep working so the admin can
 * turn the mode off. The value read is cached by the settings repository
 * (1-minute TTL). `php artisan down` is the ops-level fallback handled by the
 * framework and is independent of this middleware. When the settings table is
 * unavailable (pre-migration), the check is skipped.
 */
final class CheckMaintenanceModeMiddleware
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isExcludedPath($request) && $this->isMaintenanceOn()) {
            abort(503);
        }

        return $next($request);
    }

    private function isExcludedPath(Request $request): bool
    {
        return $request->is('admin/*')
            || $request->is('assets/*')
            || $request->is('build/*')
            || $request->is('storage/*');
    }

    private function isMaintenanceOn(): bool
    {
        try {
            return (bool) $this->settings->value(SettingName::SiteMaintenanceMode, false);
        } catch (Throwable) {
            return false;
        }
    }
}
