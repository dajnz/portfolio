# ADR-0029: Testing strategy, fixtures & seeding

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B7, B21, B31, B88, B90, B91, B107, B113, B216, B222, B322, B343
- **Supersedes:** —

## Context

The backend uses Pest against real PostgreSQL; the frontend and e2e use Vitest/Playwright. Seeding and the e2e database needed explicit rules so fixtures stay canonical, the dev database is never touched, and the Playwright suite runs deterministically.

## Decision

- Root `DatabaseSeeder` composes `SettingsSeeder` + `FeatureFlagSeeder` + single-row entities + demo content and **never touches `admins`**; admin creation is seeder-free (`admin:create`, see ADR-0027).
- Pest model factories cover all DB tables and both locales, created per-feature alongside their integration suites; `DatabaseSeeder` + `E2ETestSeeder` provide canonical fixtures. The planned Vitest typed-prop fixture factories (Faker-seeded) were never built — frontend suites use inline typed literals plus feature helpers.
- Test files are split semantically: `PortfolioControllersTest` → `LandingControllerTest`/`DashboardControllerTest`/`AboutControllerTest`/`ContactControllerTest`/`FeedbackControllerTest`; `AdminBlogManagementTest` → `AdminBlogControllerTest`/`AdminTagControllerTest`/`BlogPreviewTest`; `PublicBlogTest` → `PublicBlogIndexTest`/`PublicBlogSearchTest`/`PublicBlogArticleTest`/`ArticleViewCounterTest`/`ArticleReactionsTest`. Shared fixtures live in `tests/Support/Traits/` (`SeedsPortfolioData`, `SeedsBlogData`); Pest helper functions must be renamed per file because the suite loads in one process/namespace.
- `playwright.config.ts` lives at the **repo root** with `testDir: './tests/e2e'`; the `admin` project matches `/admin\//` and the `public` project `/public\//`.
- `browser.newContext()` inherits `use.storageState`, so credential tests explicitly pass `{ storageState: undefined }` — a login regenerates the cache-backed session and would otherwise destroy the shared `.auth/admin.json`.
- `make test-e2e` runs against a dedicated `portfolio_e2e` database plus isolated Redis DB 1, served by `php artisan serve --no-reload` on `127.0.0.1:8001`; `E2ETestSeeder` must not truncate `migrations`; the trap kills both the serve master and the `php -S` child; the server starts with `docker compose exec -d`; the admin project sets `retries: 0` (login throttle is 5/min).
- `E2ETestSeeder` has a database-name guard: it refuses to run unless the database is `portfolio_e2e` (local) or `portfolio_test` (CI).
- E2E dataset: `E2ETestSeeder` delegates to `E2EDemoContentSeeder` — bilingual articles (incl. TOC/code-block/callout, a draft and a trashed row), article tags, skill groups, published/featured projects, experience items (one current), contacts, related-article links and view counters; image-free to stay RustFS-independent; content literals live in `private const ARTICLES/PROJECTS/EXPERIENCE_ITEMS`.
- E2E execution is staged via Playwright projects with `workers: 1`: mutation-heavy admin specs before read-only public specs, `login.spec.ts` in its own `admin-auth` project, a `reauth` project re-runs `auth.setup.ts`, and settings + backup run last.
- A route must not be registered before its Inertia page component exists — a failed component resolution aborts the visit before the URL updates and breaks `auth.setup.ts` (step 4.1 temporarily shipped typed placeholder pages for `DashboardPage`/`LandingPage`).
- Audits: axe-core on every public page incl. the 404 (critical/serious fail, moderate/minor recorded as warnings) and warn-only on the admin dashboard; inline `html-validate` on the six public routes; screenshot-on-failure kept.
- E2E CI ramp: the `test-e2e` job starts with `continue-on-error: true`, flipped to blocking once stable.
- Accepted deferrals: admin list pages use the global `ErrorPage` rather than an inline `AdminEmptyState mode="error"` banner (dashboard cards and `RankingsTable` do use the inline state); `useFormHandler` is implemented and unit-tested but unused.

## Consequences

- E2E runs never touch the dev DB or the nginx stack; a bare `pnpm exec playwright test` is guarded against truncating dev data.
- Per-file fixture/helper naming is mandatory under Pest's single-process suite; the shared Playwright storage state remains the main session hazard.
