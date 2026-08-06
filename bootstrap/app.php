<?php

declare(strict_types=1);

use App\Features\Auth\Infra\Middleware\AdminOnlyMiddleware;
use App\Features\Auth\Infra\Middleware\RequireGithubLinkTotpPending;
use App\Features\Auth\Infra\Middleware\RequireSessionVersion;
use App\Features\Auth\Infra\Middleware\RequireTotpPending;
use App\Features\Auth\Infra\Providers\HorizonGateProvider;
use App\Features\Settings\Infra\Middleware\FeatureFlagMiddleware;
use App\Features\Shared\Infra\Controllers\HealthController;
use App\Features\Shared\Infra\Exceptions\ErrorResponseFactory;
use App\Features\Shared\Infra\Middleware\CheckMaintenanceModeMiddleware;
use App\Features\Shared\Infra\Middleware\CspNonceMiddleware;
use App\Features\Shared\Infra\Middleware\HandleInertiaRequests;
use App\Features\Shared\Infra\Middleware\LogContextMiddleware;
use App\Features\Shared\Infra\Middleware\RateLimitWithFingerprintMiddleware;
use App\Features\Shared\Infra\Middleware\SetLocaleFromPathMiddleware;
use App\Features\Shared\Infra\Middleware\SetLocaleMiddleware;
use App\Features\Shared\Infra\Middleware\SwaggerLocalOnlyMiddleware;
use App\Features\Shared\Infra\Middleware\ThemeMiddleware;
use App\Features\Shared\Infra\Middleware\TrailingSlashRedirectMiddleware;
use App\Features\Shared\Infra\Middleware\ValidateApiAgainstSpec;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        HorizonGateProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // Health endpoints — registered outside every middleware group:
        // no auth, no session, no locale, no CSP (devops.md:815-824).
        // `routes/web.php` cannot host them (the `web` group would apply).
        then: function (): void {
            Route::get('/up', [HealthController::class, 'up']);
            Route::get('/up/health', [HealthController::class, 'health']);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // routes.md:258-291 — middleware group composition. `log_context`
        // and `trailing_slash` are prepended so the request is captured
        // (and the 301 emitted) before any state-setting middleware runs;
        // the remaining web-group middleware is appended after the
        // framework defaults (session, CSRF — required for cookie access).
        $middleware->web(
            prepend: [
                LogContextMiddleware::class,
                TrailingSlashRedirectMiddleware::class,
            ],
            append: [
                CspNonceMiddleware::class,
                CheckMaintenanceModeMiddleware::class,
                SetLocaleMiddleware::class,
                SetLocaleFromPathMiddleware::class,
                ThemeMiddleware::class,
                HandleInertiaRequests::class,
            ],
        );

        // The `theme` and `locale` cookies are set client-side by the
        // frontend (`document.cookie`, i18n-and-theming.md:177,184-190) and
        // read back by `ThemeMiddleware`/`SetLocaleMiddleware` — Laravel's
        // cookie encryption would make them unreadable (B73).
        $middleware->encryptCookies(except: ['theme', 'locale']);

        // The `api` group already contains `throttle:api` and
        // `SubstituteBindings` (framework defaults); spec order:
        // log_context → throttle:api → validate_api_spec.
        $middleware->api(
            prepend: [
                LogContextMiddleware::class,
            ],
            append: [
                ValidateApiAgainstSpec::class,
            ],
        );

        $middleware->alias([
            'feature' => FeatureFlagMiddleware::class,
            'admin' => AdminOnlyMiddleware::class,
            'auth.session_version' => RequireSessionVersion::class,
            'totp.pending' => RequireTotpPending::class,
            'totp.pending.github_link' => RequireGithubLinkTotpPending::class,
            'locale' => SetLocaleMiddleware::class,
            'locale.path' => SetLocaleFromPathMiddleware::class,
            'theme' => ThemeMiddleware::class,
            'csp' => CspNonceMiddleware::class,
            'log_context' => LogContextMiddleware::class,
            'trailing_slash' => TrailingSlashRedirectMiddleware::class,
            'maintenance' => CheckMaintenanceModeMiddleware::class,
            'rate_limit.fingerprint' => RateLimitWithFingerprintMiddleware::class,
            'validate_api_spec' => ValidateApiAgainstSpec::class,
            // openapi.md:273-275 — the Swagger UI routes are local-only.
            'swagger.local' => SwaggerLocalOnlyMiddleware::class,
            // routes.md:288 — the session stack for the auth-required API
            // endpoints (`POST /api/upload` etc.); not in the framework's
            // default alias set.
            'encrypt_cookies' => EncryptCookies::class,
            'start_session' => StartSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON for API routes and JSON-expecting clients (e.g. the
        // `postJson`-style admin endpoints like TOTP enrollment, whose
        // OpenAPI contract documents JSON 422s).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Step 2.5 — the full error mapping (error-handling.md:53-68,
        // 175-179): JSON envelope for API/JSON requests, `ErrorPage`
        // Inertia response for `X-Inertia` requests, and `null` (fall
        // through) for Blade — the framework then renders the
        // `resources/views/errors/{status}` views. Returning `null`
        // from a render callback defers to the default handling.
        $exceptions->render(fn (Throwable $exception, Request $request): ?Response => app(
            ErrorResponseFactory::class
        )->make($exception, $request));
    })->create();
