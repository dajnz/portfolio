# ADR-0015: Error handling & response mapping

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B50, B55, B67, B68, B70
- **Supersedes:** —

## Context

Laravel 13 removed the `App\Exceptions\Handler` class and its default middleware-alias set omits names the spec assumes, while a single strategy was needed to render errors consistently across API JSON, Inertia and Blade responses. The error mapping also had to land incrementally without breaking routes shipped before the middleware aliases exist.

## Decision

- Error mapping lives in `bootstrap/app.php` `withExceptions()`, not an `App\Exceptions\Handler`: a single `render()` callback delegates to a testable `Shared\Infra\Exceptions\ErrorResponseFactory` that produces a JSON envelope → Inertia `ErrorPage` → `null` fall-through for Blade.
- `shouldRenderJsonWhen` is extended to `$request->expectsJson()` (was the scaffold's `api/*`-only mapping), so JSON-expecting admin endpoints such as TOTP enrollment / recovery-code regeneration — whose OpenAPI contract documents JSON 422s — return JSON errors. The full X-Inertia/Blade/API mapping lands in step 2.5.
- Settings admin routes ship in 2.2 without `auth`/`auth.session_version`/`admin` gating; the aliases/classes land in 2.3/2.4, which add the gating per `routes.md`.
- Health routes are registered in `bootstrap/app.php` via `withRouting(..., then:)` as bare routes (no auth/session/locale/CSP), because `routes/web.php` always applies the `web` group and the spec's "without any middleware" intent is otherwise impossible. The framework's built-in `health:` route is NOT used, since it returns `{"status":"up"}` while the spec requires `{"status":"ok"}`.
- The `encrypt_cookies`/`start_session` middleware aliases are added in `bootstrap/app.php` (Laravel 13's default alias set omits them) for auth-required API routes such as `POST /api/upload`.
- Route exceptions are rendered inside `Illuminate\Routing\Pipeline::handleException`, which continues the remaining middleware with the rendered response; an `ErrorPage` response therefore passes through `HandleInertiaRequests`' post-`$next` code, including the Inertia asset-version check. Requests without a matching `X-Inertia-Version` header get a 409 `X-Inertia-Location`.
- The 500 Blade template renders the `request_id` via the shared layout using a guarded `@if`, so the line is absent when `log_context` could not run.
- Logging: the `stack` channel's default channels are `stderr,daily` (`LOG_STACK` override) with `daily` retaining 14 days; the `stderr`/`daily` levels stay `env('LOG_LEVEL', 'debug')`, and production should achieve warning+/error+ gating via `LOG_LEVEL` while still capturing the mandated info-level events (login success, CRUD, backup start) on `stack`.

## Consequences

- All error-mapping changes happen in `bootstrap/app.php` plus `ErrorResponseFactory`; there is no `Handler` class to extend.
- Backend tests that hit web-group routes with `X-Inertia` must mirror the asset-version computation (`hash_file('xxh128', public_path('build/manifest.json'))`) or receive a 409.
- Do not rely on the framework's built-in health endpoint; the contract is the bare-route `{"status":"ok"}` in `bootstrap/app.php`.
- When `routes.md` assumes a middleware alias Laravel omits, add it in `bootstrap/app.php`.
- `LOG_LEVEL` on `stack` must not be raised so high that mandated info-level events are dropped in production.
