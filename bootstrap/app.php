<?php

declare(strict_types=1);

use App\Features\Auth\Infra\Middleware\AdminOnlyMiddleware;
use App\Features\Auth\Infra\Middleware\RequireGithubLinkTotpPending;
use App\Features\Auth\Infra\Middleware\RequireSessionVersion;
use App\Features\Auth\Infra\Middleware\RequireTotpPending;
use App\Features\Auth\Infra\Providers\HorizonGateProvider;
use App\Features\Settings\Infra\Middleware\FeatureFlagMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        HorizonGateProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'feature' => FeatureFlagMiddleware::class,
            'admin' => AdminOnlyMiddleware::class,
            'auth.session_version' => RequireSessionVersion::class,
            'totp.pending' => RequireTotpPending::class,
            'totp.pending.github_link' => RequireGithubLinkTotpPending::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON for API routes and JSON-expecting clients (e.g. the
        // `postJson`-style admin endpoints like TOTP enrollment, whose
        // OpenAPI contract documents JSON 422s). Step 2.5 defines the
        // full error mapping.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
