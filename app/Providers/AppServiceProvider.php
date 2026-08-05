<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\Infra\Listeners\LoginListener;
use App\Features\Auth\Infra\Repositories\EloquentAdminRepository;
use App\Features\Auth\Infra\Repositories\EloquentAdminTimedOtpRepository;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Settings\Infra\Repositories\EloquentFeatureFlagRepository;
use App\Features\Settings\Infra\Repositories\EloquentSettingRepository;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Infra\Repositories\EloquentTechSkillReadRepository;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TechSkillReadRepositoryInterface::class,
            EloquentTechSkillReadRepository::class,
        );

        $this->app->bind(
            SettingRepositoryInterface::class,
            EloquentSettingRepository::class,
        );

        $this->app->bind(
            FeatureFlagRepositoryInterface::class,
            EloquentFeatureFlagRepository::class,
        );

        $this->app->bind(
            AdminRepositoryInterface::class,
            EloquentAdminRepository::class,
        );

        $this->app->bind(
            AdminTimedOtpRepositoryInterface::class,
            EloquentAdminTimedOtpRepository::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // authentication.md:21-27 — 5 attempts per minute per IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('totp', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // authentication.md:333-343 — layered feedback protection: layer 1
        // is this per-IP limiter (5/min), layer 2 is the
        // `rate_limit.fingerprint` middleware (3/h per IP+UA fingerprint).
        RateLimiter::for('feedback', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // analytics.md:52 — event ingestion cap (60/min/IP).
        RateLimiter::for('analytics', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // authentication.md:363 — repopulates `session_version` after
        // remember-me (recaller cookie) re-authentication.
        Event::listen(Login::class, LoginListener::class);

        $this->commands([
            \App\Console\Commands\ExportLangCommand::class,
            \App\Features\Settings\Infra\Commands\ResetMaintenanceCommand::class,
            \App\Features\Auth\Infra\Commands\CreateAdminCommand::class,
            \App\Features\Auth\Infra\Commands\ResetAdminPasswordCommand::class,
            \App\Features\Shared\Infra\Commands\CleanupOrphanedFilesCommand::class,
            \App\Features\Shared\Infra\Commands\PruneTrashedItemsCommand::class,
        ]);
    }
}
