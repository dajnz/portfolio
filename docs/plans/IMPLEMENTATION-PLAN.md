# Implementation Plan — Portfolio with Blog

## Execution rules (read before every session)

- **Before starting any session:** re-read this file, then re-run the acceptance criteria for every step marked `[x]` or `[~]` to confirm the recorded status still matches reality. If a check fails, unmark the step and fix it before continuing.
- **After completing each step:** update its checkbox, update the "Current state" section, and commit with a message referencing the step (e.g. `Step 8.2: add blog admin CRUD backend`).
- **Branch policy:** create a separate branch per each step inside each phase from `main` (e.g. `feature/portfolio-backend`, `feature/blog-public-backend`) before starting. Never push, merge, or tag without explicit user approval.
- **If the spec changes:** update this plan file first — the affected steps and the assumptions log — before resuming implementation. Do not silently reinterpret scope.
- **If unsure whether a step is complete:** treat it as not complete and re-verify rather than assuming.
- **Command environment:** all `php artisan`, `composer`, `npm`, `npx` commands run inside the dev container (`docker compose exec dev <cmd>`); the Makefile wraps these. The localhost dev mode (`make up-localhost`, `http://localhost:8888`) is the default working environment.
- **Hard requirements:** PHP 8.5 and Laravel 13 are non-negotiable — if the environment cannot provide them, the build must halt (per `tech-stack.md`), never silently downgrade. Tests run against real PostgreSQL, never SQLite.

---

## 1. Spec reference

This plan implements the full technical specification in `docs/detailed-spec/` (21 files; entry point `docs/detailed-spec/INDEX.md`): a monolithic Laravel 13 (PHP 8.5) backend with a Vue 3 + TypeScript + Inertia.js SPA (SSR on first load), PostgreSQL, Redis, and RustFS (S3-compatible) storage. It includes a public portfolio (landing, skills, projects, experience, blog with FTS/RSS/SEO), a single-admin backend (auth with password + TOTP + GitHub OAuth, content CRUD with locale tabs, analytics, settings, resume PDF generation, and encrypted backup/restore), plus full Docker/Ansible/Swarm DevOps, CI, and a Pest/Vitest/Playwright test suite.

Already committed and authoritative (do not recreate): `docs/openapi/` (public REST + Inertia page specs — the contract single source of truth), `docs/mockups/` (visual reference incl. `public/tokens.css` and `admin/tailwind-preset.js`), `.spectral.yaml`.

## 2. Assumptions & decisions log

Defaults approved by the user 2026-08-04. Assumptions only fill spec gaps — they never override stated requirements.

| # | Decision | Status |
|---|---|---|
| A1 | **Restore is async**: HTTP-triggered restore dispatches a new queued `RestoreBackupJob` (mirroring `CreateBackupJob`); `backups.status` transitions pending→processing→completed/failed; UI polls `GET /admin/backups/{backup}/status` (~2 s) and reloads on completion. CLI `backup:restore` stays synchronous. Progress bar is indeterminate (`null` value is supported by `ProgressBar`). | User-approved |
| A2 | **Screenshots / detail-items reconciliation by `id`**: rows with existing IDs are updated; new entries (no `id`) are created; DB rows absent from the submitted array are deleted (screenshot files hard-deleted from RustFS). | User-approved |
| A3 | **Slug rule**: if the submitted slug equals the currently stored slug → regenerate from the EN title (with numeric collision suffix); if it differs → manual override, keep it. | User-approved |
| A4 | **Reorder payloads**: simple lists (contacts, skill groups) `{ "ids": [...] }` → zero-indexed `sort_order`. Skills: `{ "skills": [{ "id", "skill_group_id" }] }` in drag order → writes `sort_order` + group assignment. | User-approved |
| A5 | **TOTP recovery codes**: 10 codes, `XXXX-XXXX-XXXX` (12 chars, unambiguous alphabet), bcrypt-hashed, stored as `[{ "hash", "used" }]` in `totp_recovery_codes`. | User-approved |
| A6 | **`/cv` honors `?locale=`** query param overriding the cookie for that request only (needed by "Download preview"). | User-approved |
| A7 | **Nginx `/assets/*` mapping**: `location /assets/ { proxy_pass http://rustfs:9000/portfolio/; }` — path-style, consistent with `use_path_style_endpoint`; DB stores only `blog/...`-style paths; public URLs are `{APP_URL}/assets/{path}`. | User-approved |
| A8 | **Numeric settings bounds**: per-page settings and `backup_max_count` get `min:1`; `trash_retention_days` `min:0` (0 disables pruning, per spec). Spec only required type validation. | User-approved |
| A9 | **"Generate Now" button** on the admin Analytics dashboard (required by `analytics.md`, omitted from the admin UI spec — included). | User-approved |
| A10 | **Public nav visibility**: Blog + Experience nav links always visible; Projects link conditional on ≥1 published project; landing anchors (About/Contacts) always shown. | User-approved |
| A11 | **Skill/tag search ordering**: name ascending, limit 20, for `/api/skills/search`, `/api/tags/search`, `/api/blog-tags/search`. | User-approved |
| B1 | Generated TS types (`api.ts`, `inertia.ts`) are **committed** so `npm run build` is self-contained; regenerated via `npm run typecheck`. | User-approved |
| B2 | Admin blog list filtering reuses the public convention `?article_tags=&skill_tags=&year=` (all locales + unpublished for admin). | User-approved |
| B3 | `auth.user` shared prop shape: `{ id, name, email, login, github_linked, totp_enabled, is_oauth_primary }`. | User-approved |
| B4 | All keyed HMAC-SHA256 (IP hashes, feedback/reaction fingerprints) uses `config('analytics.ip_hash_key')` falling back to `APP_KEY`; fingerprint = HMAC of `ip|user_agent`; IP-only hash for `ip_address` columns. | User-approved |
| B5 | Old-slug 301 lookup (`{article:slug}` / `{project:slug}`) is resolved in the controllers (main table → `old_slugs` → 301), since implicit route binding cannot do the fallback. | User-approved |
| B6 | `old_slugs` recorded on any slug change (published or not) and on force-delete. | User-approved |
| B7 | Root `DatabaseSeeder` composes SettingsSeeder + FeatureFlagSeeder + single-row entities + demo content; never touches `admins`. | User-approved |
| B8 | `GET /admin/backups/{backup}/status` returns `{ "data": { "id", "status", "progress", "completed_at", "error_message" } }` per the committed `docs/openapi/inertia/backup.yaml:40-62`; `progress` is nullable (indeterminate when `null`, compatible with A1), `error_message` is present only when status is `failed`. | Fixed (was incomplete — now matches committed OpenAPI contract) |
| B9 | `lang:export` command + build-time `t()` key gate implemented as a Vite pre-build plugin. | User-approved |
| B10 | Full `lang/{en,ru}` catalogs are authored once in Phase 2.6; spec examples are illustrative only. | User-approved |
| B11 | **Cross-feature reads** (spec does not define them): landing featured projects go through a new `Shared/Domain/RepositoryInterfaces/ProjectReadRepositoryInterface` (+ `Shared/Infra` Eloquent impl, mirroring the TechSkill read pattern); dashboard stats and the project tech-writeup publish check use query-builder reads inside the consuming feature's Infra layer (no cross-feature class imports). | New (gap-fill) |
| B12 | **CSP + Inertia SSR risk**: verify that Inertia SSR output (inline page-data scripts) complies with the nonce-based CSP; if not, propagate the nonce into the SSR payload. Verified in Step 13.3. | New (risk) |
| B13 | GitHub OAuth uses Socialite defaults (no extra scopes). | Minor |
| B14 | `phpunit.xml` created with `DB_CONNECTION=pgsql`, env=testing; CI appends its own env vars. | Minor |
| B15 | `public/images/og-default.png` (1200×630) created as a committed asset (referenced by SEO spec). | Minor |
| B16 | **FTS search snippets (headline)**: Inertia HTML search results include `ts_headline()`-generated snippets with `<mark>` tags (parameters: `MaxWords=50, MinWords=20, StartSel=<mark>, StopSel=</mark>` per `database.md:676-679`). The JSON response variant returns raw field values without highlights. A shared `FtsQueryBuilder` in `Shared/Infra/Search/` provides `search()` (`plainto_tsquery` + `ts_rank(..., 32)` normalization, no `setweight`), `autocomplete()` (prefix `:*` with empty-tsquery CASE guard), and `headline()` (queries >200 chars truncated; empty query returns all). `search_vector` columns are built from `to_tsvector(dictionary, title || ' ' || brief_description || ' ' || content)` (projects: `title || ' ' || brief_description || ' ' || full_description`). | New (gap-fill — spec requires headline via `database.md:676-679` and `openapi.md:82`) |
| B17 | **Restore preserves `feature_flags`**: the table is listed in `config/backup.php` `excluded_tables`, never archived, and never truncated during restore (per `backup.md:71,74`). The current state at restore time is preserved as-is — feature flags are environment-specific. | New (gap-fill — per `backup.md:71,74`) |
| B18 | **Backup archive filename**: `backup-{Y-m-d-His}-k{id}.zip` (per `backup.md:82`), embedding `backup_encryption_keys.id` for offline key identification in the filename. | Minor |
| B19 | **Admin keyboard shortcuts**: `Ctrl+Enter` (submit current form), `Escape` (close modal/cancel editing), `/` on list pages (focus search input) — per `accessibility.md:50,265`. | Minor |
| B20 | **SMTP "test connection" button**: Mail tab of admin Settings includes a button that probes current SMTP settings without sending mail (per `architecture.md:151`). `POST /admin/settings/test-mail` endpoint added as a gap-fill (no dedicated route in `routes.md`). | Minor |
| B21 | **Model factories**: Pest model factories (for all DB tables, covering both locales) and Vitest typed-prop fixture factories (with Faker seed) are created per-feature alongside their integration test suites. `DatabaseSeeder` + `E2ETestSeeder` provide canonical fixtures (per `testing.md:193`). | Minor |
| B22 | **Scheduled command cadences**: `app:cleanup-orphaned-files` + `app:prune-trashed-items` monthly `0 3 1 * *` (per `backup.md:465`); `analytics:generate-reports` every 4h (`0 */4 * * *`); `analytics:prune-events` monthly `0 3 1 * *`; `backup:create` daily `0 2 * * *`. | Minor |
| B23 | **Content pipeline is shared**: the CommonMark+callouts+Shiki HTML cache pipeline (defined in Step 8.2) is a `Shared` service reused by Blog article detail, Projects detail (`full_description`, `problem`, `solution`), and any feature rendering Markdown content. | Minor |
| B24 | **Analytics bot UA discard semantics**: when the request User-Agent matches `NOT_BOT_USER_AGENT_PATTERN`, the **entire batch** is discarded and the endpoint returns `204` (per `analytics.md`). | Minor |
| B25 | **`useAnalytics` buffer overflow**: when `MAX_BUFFER_SIZE_BYTES` (45 KB) cap is exceeded, newest events are dropped (per `architecture.md`). | Minor |
| B26 | **TOTP invalid recovery code**: using an invalid recovery code counts as a failed TOTP attempt and increments the backoff counter (per `authentication.md`). | Minor |
| B27 | **`is_oauth_primary` formula**: `(github_id IS NOT NULL) && (password IS NULL)` — computed, not a stored column. | Minor |
| B28 | **ICU formatting** is a domain-level class `Shared\Domain\Services\IcuMessageFormatter` (wraps `\MessageFormatter::format()`), not a global `icuf()` helper — restored from the superseded plan per user confirmation; supersedes the helper-based draft of Step 2.6. | User-approved |
| B29 | **Soft-deleted entities occupy their slug** (no slug reuse); restore re-checks slug uniqueness against non-trashed entities (per content-management.md; restored from the superseded plan). | New (gap-fill) |
| B30 | **Analytics batch rejection**: any invalid event item rejects the **entire batch** (simplest and safest; restored from the superseded plan). | User-approved |
| B31 | **E2E CI ramp**: `test-e2e` job starts with `continue-on-error: true`, flipped to blocking once stable (restored from the superseded plan). | User-approved |
| B32 | **Dashboard stats semantics** (spec contradiction resolved): count cards (projects/articles) exclude trashed per `content-management.md:889`; "Total Views" = `SUM(article_views.views)` across all articles incl. unpublished + trashed; "Blog Views" = published, non-soft-deleted only per `admin-section-ui.md:216-218`. | New (spec conflict resolved) |
| B33 | **Analytics ingestion lands in 11.1**: `useAnalytics` (3.2) posts to `/api/analytics/track`; dev requests 404 until 11.1 — accepted (the endpoint may be pulled forward). | New (ordering note) |
| B34 | **"No service providers" contradiction** (`architecture.md:263`) vs the required `SettingsMailConfigProvider` (`architecture.md:157`): interpreted as "no providers beyond the documented ones". | New (spec note) |
| B35 | **`is_oauth_primary` is dead under the current schema** (`admins.password` NOT NULL, `authentication.md:329`) — derived and passed anyway per spec, for forward compatibility. | New (spec note) |
| B36 | **DR-restore success toast** (`backup.md:592`) is shown only after the async restore completes (per A1 polling), not at submit time. | New (spec sync) |
| B37 | **Analytics 90-day retention** is a fixed constant inside `AggregateAnalyticsReportsJob` (`architecture.md:171`); `config/analytics.php` retention key is informational only. | New (spec note) |
| C1 | `composer.json` `"php"` constraint is `"8.5.*"` — the spec's literal `"php": "8.5"` is an exact-version match (8.5.0 only) in Composer and breaks installs on 8.5.x; `8.5.*` preserves the "no fallback" pinning (8.5.x only, no 8.6+). | Minor (constraint fix) |
| C2 | **`@tiptap/extension-markdown` doesn't exist on npm** — the official Tiptap 3 markdown parser/serializer is `@tiptap/markdown` (3.29.2). Installed under that name; no functional difference from the spec's intent. | Minor (package name fix) |
| C3 | **TypeScript pinned to `^5.9`** (not "latest"): TypeScript 7 (native compiler) is blocked by `openapi-typescript@7`'s `peer typescript: "^5.x"`. Bump to 7.x once the typegen toolchain supports it. | Minor (toolchain constraint) |
| C4 | **`biome.json` migrated to the Biome 2.5.6 schema** (the tech-stack.md sample is 2.0-era): `files.include` → `files.includes`, `semiStyle` → `semicolons`, `singleQuote` → `quoteStyle`, `useSingleVarDeclarations` → `useSingleVarDeclarator`, `javascript.jsx` removed. Generated `Types/*.ts` are excluded from assist/formatter/linter via `overrides` (openapi-typescript output is not Biome-formatted and is regenerated on every `npm run typecheck`). Note: `overrides[].assist` must NOT be set — Biome 2.5.6 then matches no files (upstream quirk). | Minor (config migration) |
| C5 | **Vite 8 (rolldown) requires `manualChunks` as a function** — the object form fails with `TypeError: manualChunks is not a function`; `vue-vendor` chunking uses the `id.includes(...)` function form. | Minor (toolchain constraint) |

## 3. Non-goals

Explicitly out of scope (from `architecture.md` + spec statements — do not build):

- Multi-user CMS / roles / permissions / RBAC (single admin).
- Article comments, comment RSS, tag hierarchies, notifications beyond the two queued mailables, GraphQL, webhooks, social login beyond GitHub.
- Draft workflow, scheduled publishing, autosave, revisions/versioning/audit trail, file versioning.
- Public-user OAuth/registration; password-reset web flow (replaced by `admin:reset-password`).
- FTS beyond configured locales; real-time features (SSE/WebSockets).
- Offsite backup replication (risk accepted per `backup.md`).
- Image processing: thumbnails, responsive srcset, WebP/AVIF conversion, EXIF stripping, compression.
- External log aggregation (Graylog/Sentry); no self-service data deletion UI.
- Pixel-perfect visual snapshot tests; blocking admin a11y gate (warnings only).
- Multi-version API runtime support; auto-promotion of non-blocking perf budgets (stays warning-level initially).

## 4. Current state

**Step 0.1 complete** (2026-08-04): Laravel 13.23.0 scaffolded on PHP 8.5.8, all backend dependencies installed (Pest 4, Pint, Socialite, google2fa-laravel, endroid/qr-code, league/commonmark, laravel-dompdf, flysystem-aws-s3-v3, Horizon, ziggy, l5-swagger, openapi-psr7-validator), `App\Features` PSR-4 autoload, `declare(strict_types=1)` via Pint (`pint --test` exit 0), `app/Features/{Feature}/{Domain,App,Infra}` skeleton (11 features), `.editorconfig` per `tech-stack.md`. Verified on host toolchain (PHP 8.5.8, identical to the dev image spec); the `docker compose exec dev ...` acceptance run becomes available at step 0.4.

**Step 0.2 complete** (2026-08-04): full frontend toolchain installed (Vue 3.5, Pinia 4, Inertia 3, Vite 8/rolldown, Tiptap 3 + `@tiptap/markdown` (C2) + code-block-lowlight + lowlight, Shiki, @iconify/vue + simple-icons + heroicons, intl-messageformat, ziggy-js, Vitest 4 + @vue/test-utils + happy-dom + @vitest/coverage-v8, Playwright, Biome 2.5, ESLint 10 + eslint-plugin-vue, stylelint + config-standard, html-validate, axe-playwright, openapi-typescript, vue-tsc, TypeScript 5.9 (C3)); `tsconfig.json` strict + 19 feature aliases; `vite.config.ts` (aliases, `vue-vendor` manualChunks function form per C5, 1000 kB chunk budget warning, vitest config with 80% coverage thresholds); `biome.json` (2.5.6 schema per C4), `eslint.config.js`, `.stylelintrc.json`, `.htmlvalidate.json`; committed generated `resources/js/Features/Shared/CrossSurface/Types/api.ts` + `inertia.ts` (B1) from `docs/openapi/`. Verified on host toolchain (Node v24.18.0, npm 11.16): `npm ci` → `npm run typecheck` (typegen + `vue-tsc --noEmit`) exit 0; `npx biome ci resources/js` and `npx eslint resources/js` exit 0; `npm run build` green. The `docker compose exec dev ...` acceptance run becomes available at step 0.4.

---

## 5. Phases and steps

### Phase 0 — Project scaffolding & dev toolchain

- [x] **0.1 Scaffold Laravel 13 app + backend dependencies**
  Pin PHP 8.5 / `laravel/framework: ^13.0` (no lower-bound fallback), PSR-4 `App\Features\` → `app/Features/`, `declare(strict_types=1)` via Pint; install Pest, Pint, Socialite, google2fa-laravel, endroid/qr-code, league/commonmark, barryvdh/laravel-dompdf, league/flysystem-aws-s3-v3, Laravel Horizon, ziggy, l5-swagger, openapi-psr7-validator; create feature directory skeleton under `app/Features/`; `.editorconfig`.
  Files: `composer.json`, `composer.lock`, `app/`, `bootstrap/`, `config/`, `.editorconfig`
  Acceptance: `docker compose exec dev composer install && docker compose exec dev php artisan --version` shows Laravel 13 on PHP 8.5; `docker compose exec dev ./vendor/bin/pint --test` exits 0.
- [x] **0.2 Frontend toolchain + type generation**
  Install Vue 3, TypeScript (strict), Pinia, Inertia 3, Vite 8, Tiptap (+markdown, code-block-lowlight, lowlight), Shiki, @iconify/vue + simple-icons/heroicons JSON sets, intl-messageformat, ziggy, Vitest, Playwright, Biome 2, ESLint+eslint-plugin-vue, stylelint, html-validate, axe-playwright, openapi-typescript, vue-tsc; `tsconfig.json` strict + all `@*-*` feature aliases; `vite.config.ts` (aliases, `vue-vendor` manualChunks, chunk budget warning); `biome.json`, `eslint.config.js`, `.stylelintrc.json`, `.htmlvalidate.json`; `package.json` scripts (`lint`, `lint:css`, `format`, `typecheck`, `generate-api-types`, `generate-inertia-types`).
  Files: `package.json`, `tsconfig.json`, `vite.config.ts`, `biome.json`, `eslint.config.js`, `.stylelintrc.json`, `.htmlvalidate.json`
  Acceptance: `docker compose exec dev npm ci && docker compose exec dev npm run typecheck` passes (generates `api.ts`/`inertia.ts` from existing `docs/openapi/`; `vue-tsc --noEmit` exits 0); `npx biome ci resources/js` and `npx eslint resources/js` exit 0.
- [ ] **0.3 Base Laravel configuration**
  `config/auth.php` (web guard → `admins` provider → `AdminModel`), `config/session.php` (lifetime 120 min per `authentication.md:359`), `config/filesystems.php` (rustfs + rustfs-backups s3 disks, path-style), `config/portfolio.php` (per-page defaults, locales, FTS dictionary map, icon sets incl. the 22 engineering icons), `config/analytics.php` (retention, bot UA pattern, ip hash key), `config/backup.php` (encryption flag, legacy key, max_count, excluded tables), `config/services.php` (github), `phpunit.xml` (pgsql, env=testing), `.env.example` (full dev template from devops.md).
  Files: `config/*`, `phpunit.xml`, `.env.example`
  Acceptance: `docker compose exec dev php artisan config:cache && docker compose exec dev php artisan about` reports pgsql driver and no errors.
- [ ] **0.4 Docker dev environment**
  Dockerfiles (`devops/docker/php/`, `dev/`, `node/`, `nginx/` incl. `/assets/ → rustfs:9000/portfolio/` mapping per A7 + entrypoint.sh),   `docker-compose.yml` (app, nginx, db, redis, queue, scheduler, rustfs, mailpit, dev + Traefik labels), `docker-compose.localhost.yml`, `docker-compose.prod.yml`, `Makefile` (full target set per `devops.md:826-948`: up/down/up-localhost/down-localhost/restart/build/install/migrate/seed/setup/setup-localhost/test/test-unit/test-feature/test-frontend/lint/lint:css/format/typecheck/health/secrets-init/deploy/rollback), `.dockerignore`; every service configured with Docker log rotation (`json-file` driver, `max-size: 10m`, `max-file: 3` per `error-handling.md:295-304`).
  Files: `devops/docker/**`, `docker-compose*.yml`, `Makefile`, `.dockerignore`
  Acceptance: `make up-localhost` then `make health` shows all services `(healthy)`; `docker compose exec dev php -v` = 8.5 and `docker compose exec dev node -v` = 24.x.
- [ ] **0.5 CI workflows**
  `.github/workflows/ci.yml` jobs (lint, typecheck, test-backend with PostgreSQL service + coverage ≥80%, test-frontend, test-e2e with PG + RustFS services, update-manifest on main); commitlint config (conventional commits). `test-e2e` starts with `continue-on-error: true` per B31 — flipped to blocking in 13.2.
  Files: `.github/workflows/ci.yml`, `commitlint.config.js`
  Acceptance: `yamllint .github/workflows/ci.yml` (or actionlint) passes; every command referenced in the workflow runs green locally (`make lint`, `make typecheck`, `npx @stoplight/spectral-cli lint docs/openapi/openapi.yaml --ruleset .spectral.yaml` and the inertia.yaml equivalent).

### Phase 1 — Data layer (migrations)

- [ ] **1.1 Migrations: auth + config tables**
  `admins` (all auth columns incl. totp/recovery/session_version), `settings` (key PK, type), `feature_flags` (key PK).
  Files: `database/migrations/*admins*`, `*settings*`, `*feature_flags*`
  Acceptance: `docker compose exec dev php artisan migrate:fresh` succeeds; `docker compose exec dev php artisan migrate:rollback --step=3` drops all three without FK errors.
- [ ] **1.2 Migrations: portfolio + skills + contacts**
  `about_info` (+translations), `skill_groups` (+translations), `skills` (CHECK 1–5, `(skill_group_id, sort_order)` index), `contacts` (+translations) — per the spec's parallel batch order.
  Files: `database/migrations/*about_info*`, `*skill_group*`, `*skills*`, `*contact*`
  Acceptance: `php artisan migrate:fresh` succeeds; `php artisan migrate:rollback --step=<batch>` reverses cleanly (verified per migration batch).
- [ ] **1.3 Migrations: blog group**
  `blog_articles` (+translations with `search_vector` + GIN), `article_tags` (+translations), `article_tag_pivot`, `article_skill_pivot`, `article_reactions` (unique `(article_id, fingerprint)`), `article_views` (article_id PK), `related_articles` (bidirectional pair + `article_id <> related_article_id` check).
  Files: `database/migrations/*blog*`, `*article_*`
  Acceptance: `php artisan migrate:fresh` succeeds; rollback of the batch reverses cleanly.
- [ ] **1.4 Migrations: projects + experience + old_slugs**
  `projects` (+translations with `search_vector` + GIN), `project_detail_items` (+translations), `project_tech_tags`, `project_screenshots`, `experience_timeline_items` (+translations, CHECK `(project_id IS NULL OR external_project_name IS NULL)`, `ended_at >= started_at`), `experience_item_tech_tags`, `old_slugs` (unique `(old_slug, entity_type)`).
  Files: `database/migrations/*project*`, `*experience*`, `*old_slugs*`
  Acceptance: `php artisan migrate:fresh` succeeds; rollback reverses cleanly.
- [ ] **1.5 Migrations: feedback, analytics, resume, backup**
  `feedback` (index `(is_read, created_at)`), `analytics_events` (indexes `(event_type, created_at)`, `(session_id)`), `analytics_reports` (unique `(report_type, period_start, period_end)`), `resume_config` (+translations), `backup_encryption_keys` (index `(is_active)`), `backups` (FK → encryption keys `ON DELETE RESTRICT`, index `(status, created_at)`).
  Files: `database/migrations/*feedback*`, `*analytics*`, `*resume_config*`, `*backup*`
  Acceptance: `php artisan migrate:fresh` succeeds and `php artisan migrate:rollback` rolls back **all** batches in reverse order without FK violations; re-running `migrate:fresh` again is clean (idempotent).

### Phase 2 — Cross-cutting backend (Shared, Settings, Auth, error handling, i18n)

- [ ] **2.1 Shared tech-skill read model**
  `Shared/Domain` (BaseEntity/BaseValueObject, `TechSkillDTO`, `TechSkillGroupDTO`, `TechSkillAggregate`, `GroupedTechSkills`, `TechSkillReadRepositoryInterface` — final readonly classes), `Shared/Infra` Eloquent models (`SkillModel`, `SkillGroupModel`, `SkillGroupTranslationModel`), `EloquentTechSkillReadRepository` (single-join queries, inline mapping, proficiency labels via `__()`), `SearchSkillsUseCase` + `SkillSearchController` (`GET /api/skills/search`).
  Files: `app/Features/Shared/**`, `tests/unit/backend/Shared/**`, `tests/integration/backend/Shared/TechSkillReadRepositoryTest.php`
  Acceptance: `php artisan test tests/integration/backend/Shared/` passes (findAll/findByIds/findAllGrouped ordering by group+skill sort_order, locale labels, search endpoint response shape `[{id,name,icon}]`).
- [ ] **2.2 Settings & feature flags backend**
  `SettingModel`/`FeatureFlagModel`, `SettingsService` (DB with config fallback, Redis `settings:{key}` 1-min TTL + invalidation), `SettingsMailConfigProvider` (merges mail settings into `config('mail')` at boot, skips if table missing), `FeatureFlagMiddleware` (`feature:` alias), `SettingsController` + `UpdateSettingsRequest` + `type_validate` rule (atomic per-tab save, cache clear), `PATCH /admin/settings/feature-flags`, `ResetMaintenanceCommand`; `SettingsSeeder` + `FeatureFlagSeeder`; admin Settings backend routes.
  Files: `app/Features/Settings/**`, `database/seeders/SettingsSeeder.php`, `FeatureFlagSeeder.php`, `tests/integration/backend/Settings/**`
  Acceptance: `php artisan test tests/integration/backend/Settings/` passes (type validation incl. per-key bounds per A8, atomic save, cache invalidation, flag toggling, seeder key inventory matches the spec's settings key list).
- [ ] **2.3 Auth backend**
  `AdminModel` (extends `Illuminate\Foundation\Auth\User`, table `admins`), `EloquentAdminRepository` + mapper + `Admin` entity + `TotpSecret` VO, login/logout/TOTP/profile/GitHub controllers + form requests, `LoginUseCase`/`VerifyTotpUseCase` (half-auth state `totp.pending_*`, 5-min expiry, escalating backoff tiers 1–3/4–6/7–9/10–14/15+, atomic counter increments, recovery codes per A5, ±1 window), GitHub OAuth intent flows (login/link, conflict matrix, link TOTP re-auth `github_link.*` keys, session-version bump), profile update (password change, remember-me `Login` listener), middleware (`AdminOnlyMiddleware`, `RequireSessionVersion`, `RequireTotpPending`, `RequireGithubLinkTotpPending`), `admin:create` + `admin:reset-password` commands (random 24-char password, `uncompromised()` retry ×10, `--force`, non-interactive flags), auth routes + named `login` route + Horizon route gating (`auth`+`auth.session_version`+`admin`).
  Files: `app/Features/Auth/**`, `config/horizon.php`, `tests/unit/backend/Auth/**`, `tests/integration/backend/Auth/**`
  Acceptance: `php artisan test tests/integration/backend/Auth/` passes (login, half-auth lifecycle, TOTP backoff tiers, recovery codes, GitHub login/link/unlink incl. conflict matrix, session-version invalidation, `admin:create` + `admin:reset-password` happy paths).
- [ ] **2.4 Shared backend services + middleware wiring**
  `bootstrap/app.php` middleware groups + aliases (web/api order from routes.md; `locale`, `locale.path`, `theme`, `csp`, `log_context`, `trailing_slash`, `maintenance`, `rate_limit.fingerprint`, `validate_api_spec`), named limiter definitions (`login` 5/min/IP, `totp` 5/min/IP, `feedback` 5/60 s/IP, `analytics` 60/min/IP) via `RateLimiter::for()` in `AppServiceProvider::boot()` (per `authentication.md:21,212` + `routes.md:237-240`), `HandleInertiaRequests` shared props (locale, theme, auth.user, ziggy, flash, notification, errors, cspNonce, featureFlags, settings, request_id), `CspNonceMiddleware` + `CspNonce`, `SetLocale(FromPath)Middleware` (+ `Content-Language` header + cookie), `ThemeMiddleware`, `LogContextMiddleware` (request_id propagation + `SENSITIVE_FIELDS` redaction + slow-query logs), `TrailingSlashRedirectMiddleware`, `CheckMaintenanceModeMiddleware`, `UploadController` + `UploadRequest` (`/api/upload` GET-less auth'd store/delete per entity_type caps, randomized names, replacement/deletion file lifecycle), `BulkActionRequest` (shared `{"ids": [...]}` payload per `architecture.md:794`), `SitemapController`/`SitemapService` (`/sitemap.xml` Redis 24h cache + invalidation events, `/robots.txt`), `HealthController` (`/up`, `/up/health` with 5 s result cache per `devops.md:824`), `CleanupOrphanedFilesCommand`, `PruneTrashedItemsCommand`, `RateLimitWithFingerprintMiddleware`; `lang/` dirs bootstrap.
  Files: `bootstrap/app.php`, `app/Features/Shared/Infra/**`, `routes/web.php`, `routes/api.php`, `routes/console.php`
  Acceptance: `php artisan test tests/integration/backend/Shared/` passes (upload/delete with `Storage::fake('rustfs')` + auth guards, sitemap generation + invalidation, robots.txt content, health endpoints, trailing-slash 301, maintenance 503 while `/admin/*` stays up, shared-props presence asserted via a stub Inertia page).
- [ ] **2.5 Error handling backend**
  `App\Exceptions\Handler` `register()` mapping (Validation/Auth/ModelNotFound/Authorization/Throttle/NotFound/MethodNotAllowed → JSON envelope + Inertia `ErrorPage` response when `X-Inertia`, Blade otherwise), Blade error views 403/404/419/422/429/500/503 + shared `errors/layout.blade.php` (BEM inline CSS, request_id line, no-JS), logging channels (`stderr`, `daily`, `analytics`) + redaction.
  Files: `app/Exceptions/Handler.php`, `resources/views/errors/**`, `config/logging.php`
  Acceptance: `php artisan test tests/integration/backend/Shared/ErrorHandlingTest.php` passes: non-Inertia 404 → Blade view with `noindex` meta; `X-Inertia` 404 → `Shared/CrossSurface/Pages/ErrorPage` props `{status, message, request_id}`; API 404 → `{"message": ...}` JSON; 500 hides details when `APP_DEBUG=false`.
- [ ] **2.6 i18n backend**
  Full `lang/en/` + `lang/ru/` PHP catalogs (all keys referenced anywhere in the app: nav, landing, blog, projects, experience, resume, admin, errors, seo, skills proficiency, common), ICU formatting via `Shared\Domain\Services\IcuMessageFormatter` domain class (wraps `\MessageFormatter::format()` — no global helper, per B28), `lang:export` artisan command (→ `resources/js/lang/{en,ru}.json`, ICU preserved, committed).
  Files: `lang/**`, `app/Features/Shared/Domain/Services/IcuMessageFormatter.php`, `app/Console/Commands/ExportLangCommand.php`, `resources/js/lang/*.json`
  Acceptance: `php artisan lang:export` generates both JSON files; `php artisan test tests/unit/backend/Shared/IcuMessageFormatterTest.php` passes (EN/RU plural + select resolution via `IcuMessageFormatter`).
- [ ] **2.7 OpenAPI contract tests + Swagger UI**
  Pest contract suite: for every operation in `docs/openapi/openapi.yaml` (public REST + dual-response routes), send a real request and validate status/body against the schema via `league/openapi-psr7-validator`; register Swagger UI (`/docs/api`, local + auth only) via l5-swagger pointing at `docs/openapi/openapi.yaml`.
  Files: `tests/integration/backend/OpenApi/**`, `config/l5-swagger.php`
  Acceptance: `php artisan test tests/integration/backend/OpenApi/` passes with zero contract violations; `GET /docs/api` returns 200 in local env behind auth.
- [ ] **2.8 Shared admin cursor-pagination service**
  `AdminCursorPaginator` shared service per `admin-section-ui.md:124-210`: keyset queries (`WHERE id < :lastId`; composite `(sort_col, id) < (:sortValue, :cursorId)` with mandatory id tiebreaker; asc/desc comparison inversion), request forms `?page=N` or `?cursor[id]=&cursor[sort_value]=`, arbitrary page-jump via index-only keyset-positioning subquery, Redis-cached `SELECT COUNT(*)` per filter combo (30 s TTL, page-number widget only), mismatched cursor → page-1 request with warning log; consumed by the 7 admin list endpoints (projects, blog, experience, feedback, backups, contacts, tags); response shape `{items, total, per_page, current_page, sort_column, sort_direction, cursors: {first, last, page}}`.
  Files: `app/Features/Shared/Infra/Services/AdminCursorPaginator.php`, `tests/integration/backend/Shared/AdminCursorPaginatorTest.php`
  Acceptance: `php artisan test tests/integration/backend/Shared/AdminCursorPaginatorTest.php` passes (id-sort + composite-sort cursors, asc/desc, page-jump positioning, cursor reset on sort/filter change, count-cache TTL).

### Phase 3 — Frontend foundation

- [ ] **3.1 App shell + SSR**
  `resources/js/app.ts`, `app.vue` (root component, error boundary, `useAnalytics` registered once), root Blade layout `resources/views/app.blade.php` (theme-blocking nonced inline script, CSP nonce wiring, `@inertia` SSR), Inertia 3 SSR entry `bootstrap/ssr/ssr.js`, Vite SSR build config + `ssr:dev`/`ssr:build` scripts.
  Files: `resources/js/app.ts`, `app.vue`, `resources/views/app.blade.php`, `bootstrap/ssr/**`, `vite.config.ts`
  Acceptance: `npm run build` (client) and `npm run ssr:build` succeed; `npm run ssr:dev` boots the SSR server; `npm run typecheck` passes.
- [ ] **3.2 Pinia stores + core composables**
  Stores: `themeStore` (cookie), `localeStore` (cookie), `authStore`, `sidebarStore` (localStorage), `toastStore`. Composables: `useTheme`, `useI18n` (intl-messageformat, cached per key+locale), `useToast`, `useFocusManagement`, `usePrefetch` (hover/visibility + debounce + AbortController), `useUnsavedChanges`, `useReducedMotion`,   `useAnalytics` (session UUID, 30 s flush, `MAX_BUFFER_SIZE` 80 + 45 KB byte cap, keepalive flush, teardown; posts to `/api/analytics/track`, which is implemented in 11.1 — dev requests 404 until then, accepted per B33), `useFormHandler` (admin), `useReadingProgress` (75% trigger), `useAutoMarkRead`.
  Files: `resources/js/Features/Shared/CrossSurface/{Stores,Composables}/**`, `tests/unit/frontend/Shared/**`
  Acceptance: `npx vitest run tests/unit/frontend/Shared/` passes (theme cookie persistence, i18n fallback + ICU plural, analytics batching/flush/byte-cap, prefetch debounce/cancel, unsaved-changes guards).
- [ ] **3.3 Public base components + layout**
  All `PublicBase*` components (Button, Input, Textarea, Checkbox, RadioGroup, Dropdown, Badge, Modal, ToastContainer, Card, Skeleton, EmptyState), `PublicGoTop`, `PublicCenteredContainer`, `PublicPageLayout`/Header/Footer (nav_items with `condition`, skip-link, landmarks, focus trap in modal, `data-testid` convention), shared public components `PublicSearchAutocomplete`, `PublicTagFilter` (multi-tag pills + `type` discriminator), `InfiniteScrollLoader`, `HorizontalCarousel` (contracts per `public-section-components.md:338-675`; consumed by 9.2–9.4), `CookiesNotice`, `ConsoleGreeting` (dev/flag-gated nonced script), public CSS custom-property tokens mirrored from `docs/mockups/public/tokens.css`.
  Files: `resources/js/Features/Shared/Public/**`, `tests/integration/frontend/Shared/**`
  Acceptance: `npx vitest run tests/integration/frontend/Shared/` passes (render/emit/error/disabled states, modal focus trap, toast ARIA roles); `npx stylelint "resources/js/**/*.vue"` reports no errors.
- [ ] **3.4 Admin base components + layout**
  All `AdminBase*` mirrors + `AdminBaseSwitch`, `ProgressBar`, `ConfirmationDialog` (password-required mode), `DropzoneUpload` (drag-over, preview, progress, per-entity max sizes), `BulkActionBar`, `DataTable` (sortable columns, zebra rows, selection, `AdminEmptyState` integration, trashed styling), `PaginationControls` (page-number rules + cursor prev/next, consuming the cursor map from the shared paginator per 2.8), `LocaleTabs`, `AdminIconPicker` (brands/engineering/ui sets from `config/portfolio.php`), `TextEditor` (Tiptap: CommonMark, callouts, code block language, image upload event), `AdminSearchAutocomplete` (dual-response JSON search per `admin-section-components.md:557`), `AdminTechSkillPicker` (incl. inline single-select attach mode per `admin-section-components.md:587`), `AdminBlogTagPicker` (unified tag+skill source with `type` discriminator per `admin-section-components.md:623`), `AdminPageLayout`/Header/Sidebar (collapsible groups, Horizon link as plain `<a>`, sticky sidebar)/Footer/GoTop/CenteredContainer; Tailwind "Ledger" preset wired from `docs/mockups/admin/tailwind-preset.js` with `darkMode: 'class'`. Admin keyboard shortcuts wired globally (B19): `Ctrl+Enter` submit, `Escape` close modal/cancel edit, `/` focus search on list pages.
  Files: `resources/js/Features/Shared/Admin/**`, `tailwind.config.js`, `tests/integration/frontend/Shared/**`
  Acceptance: `npx vitest run tests/integration/frontend/Shared/` (admin specs) passes; `npx eslint resources/js` and `npx biome ci resources/js` clean; `npm run build` applies the preset without errors.
- [ ] **3.5 Cross-surface shared components + SEO**
  `ThemeSwitch`, `LanguageSelector` (class-prop styling, public-only analytics), `TechSkillTag`, `TechSkillAutocomplete`, `SortableList` (native HTML5 DnD, cross-surface via `classes` prop, `item_key`/`handle`/`axis`, per `public-section-components.md:313-334` — reused by admin reorder lists in 4.2/5.2/6.2/7.2), `ErrorPage.vue`, `SeoUpdater` composable (Inertia `<Head>` sync + JSON-LD insert with CSP nonce), `SeoProps` typed from generated `inertia.ts`, `public/images/og-default.png` (1200×630, committed).
  Files: `resources/js/Features/Shared/CrossSurface/Components/**`, `public/images/og-default.png`, `tests/integration/frontend/Shared/**`
  Acceptance: `npx vitest run tests/integration/frontend/Shared/` (cross-surface specs) passes; `vue-tsc --noEmit` clean with `SeoProps` usage typed against generated schemas.
- [ ] **3.6 Auth admin UI + e2e harness**
  `LoginPage` (LoginCard, GitHub button flag-gated, theme/lang controls), `TotpVerificationPage` (code/recovery toggle), `ProfilePage` (+`PasswordChangeForm`, `TOTPSetup` with inline SVG QR prop, `SocialAccounts`); Playwright harness: `playwright.config.ts`, `global-setup.ts`, `fixtures/seed.ts`, `auth.setup.ts`; `E2ETestSeeder` (idempotent `TRUNCATE ... RESTART IDENTITY CASCADE`, known admin credentials, never run in prod).
  Files: `resources/js/Features/Auth/Admin/**`, `tests/e2e/{playwright.config.ts,global-setup.ts,fixtures/**,admin/login.spec.ts}`, `database/seeders/E2ETestSeeder.php`
  Acceptance: `PLAYWRIGHT_BASE_URL=http://localhost:8888 npx playwright test tests/e2e/admin/login.spec.ts` passes (invalid creds error, valid creds → dashboard redirect, logout, TOTP page redirects when not pending, session-version forced logout).

### Phase 4 — Portfolio feature

- [ ] **4.1 Portfolio backend**
  Domain entities/VOs (`Contact`, `ContactTranslation`, `AboutInfo`, `AboutInfoTranslation`, `Feedback`, `AvailabilityStatus`) + repository interfaces; App use cases (landing data, about CRUD, contacts CRUD + reorder, feedback submit/list/show/read/forwarded/destroy); Infra models, mappers, repositories, controllers, form requests (`SubmitFeedbackRequest` incl. honeypot per session-HMAC name, `UpdateAboutRequest`, `StoreContactRequest`, `ReorderContactsRequest`), routes (landing, `/admin/about`, `/admin/contacts*`, `/admin/feedback*`, `/admin`, `/api/feedback`), feedback layered rate limiting (`throttle:feedback` 5/60 s/IP + `RateLimitWithFingerprint` 3/h + honeypot), `NotifyAdminOfFeedbackMail` (gated by `email_on_feedback`, excerpt 120 chars), dashboard stats (query-builder counts per B11), `GetLandingPageDataUseCase` via `TechSkillReadRepositoryInterface` + `ProjectReadRepositoryInterface` (B11).
  Files: `app/Features/Portfolio/**`, `tests/unit/backend/Portfolio/**`, `tests/integration/backend/Portfolio/**`
  Acceptance: `php artisan test tests/integration/backend/Portfolio/` passes (landing props via `assertInertia` incl. shared props, about/contact CRUD + reorder validation, feedback submission + honeypot silent reject + rate limits + `Mail::fake()` dispatch, dashboard stats per B32: count cards exclude trashed; Total Views = `SUM(article_views.views)` incl. unpublished + trashed; Blog Views = published, non-trashed only).
- [ ] **4.2 Portfolio admin UI**
  `DashboardPage` (+`StatsCards`, `QuickLinks`), `AboutEditPage` (LocaleTabs + DropzoneUpload photo + locale-tabbed alt text + resume summary link), `ContactsListPage` (SortableList + inline icon picker brands + include-in-resume), `FeedbackListPage`/`FeedbackDetailPage` (auto mark-read, forward `mailto:` best-effort, delete confirmation).
  Files: `resources/js/Features/Portfolio/Admin/**`, `tests/integration/frontend/Portfolio/**`
  Acceptance: `npx vitest run tests/integration/frontend/Portfolio/` passes; `npm run typecheck` clean.
- [ ] **4.3 Settings admin UI**
  `SettingsPage` (General/Mail/Feature Flags/Analytics tabs; visual groups per spec: Site Status, Languages, Pagination, Data Retention, SMTP Connection with "Test Connection" button per B20, Notifications, Reports; `AdminBaseSwitch` for maintenance mode; grouped `{settings: {key: value}}` payloads; flags via `PATCH`), `FeatureFlagToggle`.
  Files: `resources/js/Features/Settings/Admin/**`, `tests/integration/frontend/Settings/**`
  Acceptance: `npx vitest run tests/integration/frontend/Settings/` passes (tab payload construction, switch semantics, flag toggle emit).

### Phase 5 — TechSkills feature

- [ ] **5.1 TechSkills backend**
  `TechSkill`/`TechSkillGroup`(+translation) entities, `ProficiencyLevel` VO, write repositories + mappers (over shared Eloquent models), use cases (skill/group CRUD, reorder, cross-group move per A4), controllers + requests (`StoreSkillRequest` incl. `unique_across_tables` rule vs article_tags, proficiency 1–5, `StoreSkillGroupRequest`, reorder requests), routes.
  Files: `app/Features/TechSkills/**`, `tests/integration/backend/TechSkills/SkillsManagementTest.php`
  Acceptance: `php artisan test tests/integration/backend/TechSkills/` passes (CRUD, drag reorder payload A4, cross-group move persists group + order, name-uniqueness across `skills`↔`article_tags`).
- [ ] **5.2 TechSkills admin UI**
  `SkillsListPage` (+`SkillGroupCard`, `SkillItem`) with inline editing, `SortableList` drag within/between groups, move up/down keyboard alternatives (a11y), proficiency control, include-in-resume checkbox, `ConfirmationDialog` deletes, `AdminIconPicker` (ui set).
  Files: `resources/js/Features/TechSkills/Admin/**`, `tests/integration/frontend/TechSkills/**`
  Acceptance: `npx vitest run tests/integration/frontend/TechSkills/` passes; `npm run typecheck` clean.

### Phase 6 — Experience feature

- [ ] **6.1 Experience backend**
  `TimelineItem`(+translation) entities/VOs, repository + mappers, use cases (list/get/store/update/destroy/restore/force + public timeline), `StoreExperienceRequest` (dates, `is_current` → `ended_at = null`, mutual-exclusion `project_id` vs `external_project_name`, achievements array validation, `short_description` plain-text length after Markdown strip), controllers + routes (public timeline + admin CRUD).
  Files: `app/Features/Experience/**`, `tests/integration/backend/Experience/**`
  Acceptance: `php artisan test tests/integration/backend/Experience/` passes (CRUD + soft-delete/restore/force, is_current cross-field rule, project/external exclusivity, public page exposes ordered published items with tag union data).
- [ ] **6.2 Experience admin UI**
  `ExperienceListPage` (DataTable + date sort + search + trashed), `ExperienceFormPage` (`ProjectSearchCombobox`, `AchievementList`/`AchievementItem` reorder, dates + Current checkbox, `AdminTechSkillPicker`, include-in-resume, published toggle).
  Files: `resources/js/Features/Experience/Admin/**`, `tests/integration/frontend/Experience/**`
  Acceptance: `npx vitest run tests/integration/frontend/Experience/` passes; `npm run typecheck` clean.

### Phase 7 — Projects feature

- [ ] **7.1 Projects backend**
  Entities/VOs + repository + mappers (incl. screenshots, detail items, translations), `ProjectReadRepositoryInterface` in Shared + `EloquentProjectReadRepository` (B11), use cases (CRUD, restore/force, publish/unpublish + bulk, feature/unfeature + `featured_sort_order` default max+1, `reorder-featured` atomic transaction, tech-writeup link + published check per B11, tag attach/detach with 409s + `tech_tags_count` pivot events, search w/ FTS headline snippets per B16, public list/detail), `StoreProjectRequest` (slug regex + uniqueness, per-locale rules, screenshots/detail-items arrays, `unique_across_tables`-style checks), controllers + routes (admin list dual-response JSON, preview signed URLs with 1 h expiry, public pages), old-slug 301 resolution (B5/B6). Project detail content (`full_description`, `problem`, `solution`) renders via the shared content pipeline (B23 — defined in Step 8.2).
  Files: `app/Features/Projects/**`, `app/Features/Shared/{Domain/RepositoryInterfaces,Infra/Repositories}/**Project*`, `tests/integration/backend/Projects/**`
  Acceptance: `php artisan test tests/integration/backend/Projects/` passes (full CRUD incl. screenshots/detail-items reconcile per A2, quick actions incl. 409s and `tech_tags_count` maintenance, bulk ops, featured reorder, preview signed URL bypassing `is_published`, public listing w/ cursor + filters + complexity sort, old-slug 301, unpublished projects return 404 on the public route — not 200 + noindex).
- [ ] **7.2 Projects admin UI**
  `ProjectsListPage` (filters bar, table/grid toggle, inline feature star, inline tag attach/remove, bulk bar, trashed view, featured `SortableList` reorder), `ProjectFormPage` (tabbed sections: Basic/Content with TextEditor/Media with screenshots gallery reorder + locale-tabbed captions/Meta/Relations/Engineering Details via `EngineeringDetailsList`).
  Files: `resources/js/Features/Projects/Admin/**`, `tests/integration/frontend/Projects/**`
  Acceptance: `npx vitest run tests/integration/frontend/Projects/` passes; `npm run typecheck` clean.

### Phase 8 — Blog feature

- [ ] **8.1 Blog persistence + admin backend**
  Entities/VOs (`Article`, `ArticleTranslation`, `ArticleTag`, `ArticleTagTranslation`, `ArticleReaction`, `ReadingTime`, `ReactionType`), mappers + repositories, `ArticleTranslationModel` `saved` events: recompute per-locale `search_vector` from `to_tsvector(dictionary, title || ' ' || short_description || ' ' || content)` (dictionary resolved from `config('portfolio.fts.locales')` per locale) + `reading_time_minutes = ceil(word_count / 200)` (per `database.md:357`), admin use cases (list w/ dual-response JSON, get/create/update/destroy/restore/force, bulk ops, publish/unpublish, tag CRUD, related-articles attach/detach in a transaction + 409s, skill-tag attach/detach + 409s), `StoreArticleRequest`/`StoreTagRequest` (per-locale required, `not_in` self for related, `unique_across_tables` tag↔skill, slug rules), `AdminTagController` (+ `POST /api/tags` `storeApi` for inline creation), signed preview routes.
  Files: `app/Features/Blog/{Domain,App,Infra}/**`, `tests/unit/backend/Blog/**`, `tests/integration/backend/Blog/AdminBlogManagementTest.php`
  Acceptance: `php artisan test tests/integration/backend/Blog/AdminBlogManagementTest.php` passes (CRUD + translations, bulk, publish sets `published_at` once, related bidirectional transaction + duplicate 409, skill-tag 409, tag uniqueness vs skills, JSON list variant incl. unpublished + all locales).
- [ ] **8.2 Blog public backend + search + feeds**
  Public use cases (list w/ infinite-scroll cursor + `?q=` FTS `plainto_tsquery`+`ts_rank` + `article_tags`/`skill_tags` intersect + `?year=`, article detail w/ shared content pipeline: safe-mode CommonMark + callouts + server-side Shiki + Redis `article:html:{id}:{locale}:{updated_at}` cache (per B23 — this pipeline is shared and reused by Projects 7.1), view-counter `IncrementArticleViewJob` w/ bot blocklist + atomic upsert, reactions endpoint w/ fingerprint + upsert/undo, SEO props + old-slug 301), `PublicBlogController` + `FeedController` (`/rss` 20 items w/ categories + enclosure), autocomplete APIs (`/api/tags/search`, `/api/blog-tags/search` unified w/ `type` discriminator). Shared `FtsQueryBuilder` (B16) in `Shared/Infra/Search/` provides `search()`, `headline()`, and `autocomplete()` for both blog and project public lists.
  Files: `app/Features/Blog/Infra/{Controllers,Jobs,Requests,routes.php,api_routes.php}`, `app/Features/Shared/Infra/Services/SearchService.php`, `tests/integration/backend/Blog/{PublicBlogTest.php,FeedTest.php}`
  Acceptance: `php artisan test tests/integration/backend/Blog/PublicBlogTest.php tests/integration/backend/Blog/FeedTest.php` passes (cursor pagination + tag intersect + year filter, FTS ranking + `ts_headline` snippets with `<mark>` per B16 + empty/stop-word edge cases, content pipeline strips raw HTML + highlights code + renders callouts, view job skips bots + atomic increment, reaction toggle/undo + fingerprint uniqueness, RSS/sitemap content, old-slug 301, unpublished articles return 404 on the public route — not 200 + noindex).
- [ ] **8.3 Blog admin UI**
  `BlogListPage` (filters, inline publish, related-articles chips + searchable single-select picker, tags column, views column, bulk bar, trashed), `BlogFormPage` (two-panel: `TextEditor` left, meta sidebar right w/ locale tabs, lead image dropzone + alt text, unified tag picker → `tags`/`skill_tags` fields, related multi-select, featured/published toggles, read-only `published_at`, preview button), `TagListPage`/`TagFormPage`.
  Files: `resources/js/Features/Blog/Admin/**`, `resources/js/Features/Blog/Shared/**`, `tests/integration/frontend/Blog/**`
  Acceptance: `npx vitest run tests/integration/frontend/Blog/` passes; `npm run typecheck` clean.

### Phase 9 — Public pages

- [ ] **9.1 Landing page**
  `LandingPage` (layout `centered:false`) + `AboutBlock`, `SkillsBlock`/`SkillGroup`/`SkillItem` (masonry 1/3–2/3 alternating rows, proficiency tooltip), `FeaturedProjectsSection` (compact cards, conditional "View all projects" based on published-count comparison), `ContactsBlock` (single-column contacts left of `FeedbackForm`), `FeedbackForm` 5-state machine, `useLandingPage`, analytics wiring (CTA events), SEO props + JSON-LD (Person/WebSite + SearchAction), prefetch rules, lazy-loaded feedback section.
  Files: `resources/js/Features/Portfolio/Public/**`, `tests/integration/frontend/Portfolio/LandingPage.spec.ts`
  Acceptance: `npx vitest run tests/integration/frontend/Portfolio/` passes; `php artisan test tests/integration/backend/Portfolio/LandingPageTest.php` green (SSR props incl. `seo`, `skills`, `featuredProjects`, `contacts`, flags).
- [ ] **9.2 Experience timeline page**
  `ExperienceTimelinePage` + `Timeline`/`TimelineItem` (alternating sides, fading last 50 px line, mobile single-column), `TagPopupModal`, `ExperienceDetailModal`, tag-union rendering, infinite scroll + empty/no-results states.
  Files: `resources/js/Features/Experience/Public/**`, `tests/integration/frontend/Experience/**`
  Acceptance: `npx vitest run tests/integration/frontend/Experience/` passes; cursor param restores state server-side (covered by 6.1 integration test).
- [ ] **9.3 Projects index + detail pages**
  `ProjectsIndexPage` (`ProjectsHeader`: search autocomplete, `PublicTagFilter` over skills, sort dropdown date/complexity, grid/list toggle with `projects_view_mode` cookie), `ProjectDetailPage` (ScreenshotsGallery + lazy `ProjectLightbox`, `EngineeringDetailItem`, tech-writeup card only when published, JSON-LD SoftwareSourceCode + BreadcrumbList, SEO), `ProjectCard` + `ProjectCardCarousel`.
  Files: `resources/js/Features/Projects/Public/**`, `tests/integration/frontend/Projects/**`
  Acceptance: `npx vitest run tests/integration/frontend/Projects/` passes; `npm run typecheck` clean.
- [ ] **9.4 Blog index + article pages**
  `BlogIndexPage` (`BlogHeader`: search autocomplete, unified `PublicTagFilter` w/ type discriminator, conditional year filter, grid/list toggle w/ `blog_view_mode` cookie), `BlogArticlePage` (`ArticleContent` w/ progressive-enhancement mounting of `CodeBlock`/`CalloutBlock`, `TableOfContents` h2/h3 sticky + mobile, `ArticleReactions` flag-gated, `RelatedPosts`, reading progress, views count), `ArticleCard`, SEO/JSON-LD (BlogPosting, BreadcrumbList, article tags incl. skills).
  Files: `resources/js/Features/Blog/Public/**`, `resources/js/Features/Blog/Shared/Composables/**`, `tests/integration/frontend/Blog/**`
  Acceptance: `npx vitest run tests/integration/frontend/Blog/` passes (incl. TOC extraction h2/h3-only, reaction undo flow, tag pill filtering state); `npm run typecheck` clean.

### Phase 10 — Resume feature

- [ ] **10.1 Resume backend**
  `ResumeConfig`(+translation) entities, `ResumeMode` VO, repository + mappers, use cases (get/edit/update, `GetResumePdfUseCase` w/ content-hash caching, `RegenerateResumePdfUseCase`), `ResumePdfRenderer` (3 Blade templates `modern/classic/compact` + dompdf, per-locale generation), Redis `resume:pdf:{locale}:{hash}` + RustFS storage, invalidation events on source models, `UpdateResumeConfigRequest` (layout JSON schema validation incl. order 0–4, mode rules, `custom_resume_file` required in manual), `/cv` (auto/manual + `?locale=` per A6), `POST /api/resume/regenerate` (auth'd, clears keys + deletes `about/resume_*.pdf`), orphan handling in `app:cleanup-orphaned-files`, about-page resume summary data.
  Files: `app/Features/Resume/**`, `resources/views/resume-templates/**`, `tests/integration/backend/Resume/**`
  Acceptance: `php artisan test tests/integration/backend/Resume/` passes (layout validation, hash-cache hit/miss w/ `Storage::fake('rustfs')`, regenerate clears + regenerates, manual mode serves uploaded file, `/cv` honors `?locale=`, fallback to `short_pitch`).
- [ ] **10.2 Resume admin UI**
  `ResumeEditPage` + `ResumeSectionEditor` (auto-save layout patches, explicit Save for About Me text, mode radio, template dropdown, inclusion summary links, Regenerate button, Download preview `/cv?locale=`), manual-mode dropzone (PDF, 5120 KB).
  Files: `resources/js/Features/Resume/Admin/**`, `tests/integration/frontend/Resume/**`
  Acceptance: `npx vitest run tests/integration/frontend/Resume/` passes; `npm run typecheck` clean.

### Phase 11 — Analytics feature

- [ ] **11.1 Analytics backend**
  Entities/repositories/mappers (`AnalyticsEvent`, `AnalyticsReport`), `TrackEventUseCase` + `AnalyticsController@track` (batch validation caps: 100 events, 50 KB, 500-char `page_url`, 2 KB referrer, seconds 0–86400, percentage 0–100; rate-limited via `throttle:analytics` 60/min/IP; any invalid event item → reject the entire batch per B30; bot UA blocklist discard; HMAC IP hashing), `GenerateReportsUseCase` + `AggregateAnalyticsReportsJob` (period boundaries per `analytics_report_frequency` default weekly, all four report types on the same cadence, `COUNT(DISTINCT ip_hash)`, upsert, prune >90 days), `SendAnalyticsReportMail` (HTML table), `analytics:generate-reports` (everyFourHours, boundary check) + `analytics:prune-events` monthly, dashboard use case + controller, scheduled command wiring in `routes/console.php`.
  Files: `app/Features/Analytics/**`, `tests/unit/backend/Analytics/**`, `tests/integration/backend/Analytics/**`
  Acceptance: `php artisan test tests/integration/backend/Analytics/` passes (ingestion caps + bot filter, report generation at boundaries for daily/weekly/monthly, unique-visitor count via ip_hash, 90-day pruning, email dispatch, dashboard data).
- [ ] **11.2 Analytics admin UI**
  `AnalyticsDashboardPage` (tabs Overview/Blog/Projects/Feedback, `AnalyticsChart` SVG w/ textContent-only data, `StatsCards`, DataTables for top articles/projects, "Generate Now" button per A9), `useAnalytics` composable wiring for dashboard charts (lazy-loaded).
  Files: `resources/js/Features/Analytics/Admin/**`, `tests/integration/frontend/Analytics/**`
  Acceptance: `npx vitest run tests/integration/frontend/Analytics/` passes (chart renders from props, XSS-safe textContent rendering, Generate Now emit); `npm run typecheck` clean.

### Phase 12 — Backup feature

- [ ] **12.1 Backup domain + contracts**
  `Backup`/`EncryptionKey` entities, `BackupStatus`/`Manifest` VOs, repository interfaces, App-layer contracts (`DbTableBatchReadable/Writable/Importable/Exportable`, `BackupArchiveInterface`, `AssetPathCollectorInterface`, `RustFsTransferInterface`).
  Files: `app/Features/Backup/{Domain,App/Contracts}/**`, `tests/unit/backend/Backup/**`
  Acceptance: `php artisan test tests/unit/backend/Backup/` passes (state transitions, manifest parsing).
- [ ] **12.2 Backup infra services**
  `JsonlTableSerializer`, `LaravelDbTableBatchReader/Writer` (chunked, settings upsert), `ZipBackupArchive` (AES-256 per-entry, validate/extract/readManifest, encryption-disabled path; filename convention `backup-{Y-m-d-His}-k{id}.zip` per B18), `RustFsAssetPathCollector` (ListObjectsV2 pagination), `RustFsBatchTransfer`, `StorageEstimator` (2× pre-flight).
  Files: `app/Features/Backup/Infra/{Serialization,Archive,Storage}/**`, `tests/unit/backend/Backup/{JsonlTableSerializerTest,ZipBackupArchiveTest,StorageEstimatorTest}.php`
  Acceptance: `php artisan test tests/unit/backend/Backup/` passes (round-trip export/import on a temp PG table, AES-256 zip round-trip incl. wrong-key rejection, estimator 2× rule).
- [ ] **12.3 Backup workflows + commands + API**
  `BackupWorkflowService`/`RestoreWorkflowService` (full 10-step/10-step pipelines incl. temp dir under `storage/app/backups/tmp/`, manifest, maintenance mode on restore + auto-lift, truncate order child-first + analytics wipe, **`feature_flags` preserved untouched per B17** — never truncated, never overwritten, asset mirror + delete-extras, settings upsert, cache/sitemap refresh), `CreateBackupJob` (+ `RestoreBackupJob` per A1), commands (`backup:create` daily 02:00, `backup:restore` + DR `--path/--key`, `backup:prune`, `backup:generate-key`), use cases (create/download/delete/restore/import-from-path + encryption-key CRUD with active-key invariant + ON DELETE RESTRICT), controllers + requests (`RestoreBackupRequest` w/ `current_password`, upload DR form w/ key validation + `backup_upload_max_size`), status endpoint, routes.
  Files: `app/Features/Backup/{App,Infra}/**`, `routes/console.php`, `tests/integration/backend/Backup/**`
  Acceptance: `php artisan test tests/integration/backend/Backup/` passes (create → archive exists + record completed + prune, restore round-trip incl. settings upsert + analytics wipe + `feature_flags` preserved per B17 + asset mirror, DR import with key, download stream, delete rules, password-confirm validation, CLI `backup:restore` round-trip).
- [ ] **12.4 Backup admin UI**
  `BackupListPage` (`BackupStats`, DataTable w/ status badges + download/delete, `EncryptionKeyList`, `KeyGenerationModal` show-once + copy, `RestoreForm` w/ password + indeterminate `ProgressBar` + polling of status endpoint, `UploadRestoreForm` DR flow w/ errors).
  Files: `resources/js/Features/Backup/Admin/**`, `tests/integration/frontend/Backup/**`
  Acceptance: `npx vitest run tests/integration/frontend/Backup/` passes (polling loop, show-once key modal, DR form validation states); `npm run typecheck` clean.

### Phase 13 — Production DevOps, E2E, performance, final verification

- [ ] **13.1 Ansible + secrets management**
  `devops/deploy/{hosts.yml,deploy.yml,rollback.yml,manifest.yml}`, `devops/secrets/init.sh` + `age.pub` bootstrap (idempotent generation, `CHANGE_ME` validation gate, age encryption + round-trip verify, never prints values), Makefile `secrets-init`/`deploy`/`rollback` targets, `.gitignore` entries, production `.env` overrides table.
  Files: `devops/deploy/**`, `devops/secrets/**`, `Makefile`, `.gitignore`
  Acceptance: `bash -n devops/secrets/init.sh` passes; `make secrets-init` runs twice and the second run leaves `.env.prod.local` byte-identical (`.env.prod.age` may differ); `ansible-playbook --syntax-check -i devops/deploy/hosts.yml devops/deploy/deploy.yml` passes on a host with ansible.
- [ ] **13.2 E2E suite**
  All spec files: `tests/e2e/public/{landing,blog,projects,experience,feedback,layout,feeds}.spec.ts` and `tests/e2e/admin/{dashboard,blog-management,project-management,experience-management,skills-management,settings,backup}.spec.ts`, axe-core audits (critical/serious blocking on public, warnings on admin), `html-validate` inline checks on public pages, `data-testid` locators only, screenshot-on-failure.
  Files: `tests/e2e/**`
  Acceptance: `PLAYWRIGHT_BASE_URL=http://localhost:8888 npx playwright test` passes fully (all projects, incl. backup create/download/delete/DR-restore flow and login/TOTP/session-version scenarios); `test-e2e` CI job flipped from `continue-on-error: true` to blocking (closes B31).
- [ ] **13.3 Performance, SEO & CSP verification**
  Lighthouse CI step with `resource-summary` budgets per page (per `architecture.md` Performance Budget: Landing 150 KB, Blog index 180 KB, Blog article 200 KB, Projects index 170 KB, Project detail 190 KB, Experience 160 KB gzipped JS; initially non-blocking warnings), Vite chunk-size budget warnings, lazy-loading rules (Shiki async, lightbox async, feedback async, dashboard charts async), image-loading attributes audit (hero `fetchpriority="high"`, below-fold `loading="lazy"`, explicit `width`+`height` on every `<img>` per architecture.md's Image Loading table), Core Web Vitals sanity run, CSP+SSR nonce verification (B12), sitemap/RSS/robots/hreflang/canonical checks, `app:cleanup-orphaned-files --dry-run` run.
  Files: `.github/workflows/ci.yml` (lighthouse job), `lighthouse-config/`, spot-check docs
  Acceptance: `npm run build` reports no page chunk exceeding the budgets enumerated above; a local Lighthouse run on landing/blog/projects shows gzipped JS within budget (warnings allowed); DOM audit confirms every `<img>` across all public pages has the correct `fetchpriority`/`loading`/`width`/`height` attributes; `/sitemap.xml`, `/rss`, `/robots.txt` return correct content; axe on all public pages has zero critical/serious violations.
- [ ] **13.4 Final verification pass**
  Full suite run: `make lint`, `make typecheck`, `make test` (Pest incl. OpenAPI contract tests + coverage ≥80%, Vitest incl. coverage ≥80%, Playwright), `php artisan migrate:fresh --seed`, backup drill (`backup:create` → `backup:restore` CLI round-trip → public pages render), Horizon dashboard reachable and gated, `php artisan config:cache && route:cache && view:cache` in production mode succeed.
  Files: repo-wide
  Acceptance: all suites green, coverage thresholds met, restore drill completes and public pages render after restore; every step above marked `[x]` with its acceptance re-verified.
- [ ] **13.5 Local development onboarding verification**
  Follow `devops.md`'s environment setup steps exactly on a clean machine/VM (fresh `git clone` → running local instance with seed + passing test suites).
  Files: docs only
  Acceptance: a new contributor can go from `git clone` to a working local instance using only documented steps, with no undocumented manual fixes.
- [ ] **13.6 Manual accessibility pass**
  Full keyboard navigation (public + admin), screen-reader spot-check (VoiceOver/NVDA) on landing, blog index/article, project detail, experience timeline, feedback; dark + light contrast check on every page type per accessibility.md's Manual Testing section; keyboard-alternative audit of all drag-and-drop reorder lists.
  Files: repo-wide (manual checklist)
  Acceptance: checklist completed with no unresolved issues; every sortable list (skills, contacts, screenshots, featured projects) reorderable via keyboard-only move-up/move-down buttons with correct `aria-label`s and `aria-disabled` at boundaries.
- [ ] **13.7 i18n completeness check**
  Every UI string uses `t()` or `__()` — no hardcoded English strings in `.vue` templates or Blade views (error pages may keep a static English fallback per seo.md). Both RU and EN JSON catalogs are complete.
  Files: `resources/js/**`, `resources/views/**`, `lang/**`, `resources/js/lang/*.json`
  Acceptance: grep check of `.vue` templates and Blade views for literal English text outside `t()`/`__()` (heuristic, human-reviewed); manual click-through in RU locale confirms no untranslated strings.
- [ ] **13.8 Final spec-coverage audit**
  Cross-check every requirement in all 21 spec files against completed steps. Confirm no silent omissions.
  Files: repo-wide (this plan + the §6 traceability table)
  Acceptance: the §6 traceability table is verified against actual step completion; any gap found is either fixed or logged in the Assumptions & Decisions log as a deliberate deferral.

---

## Dependency summary

```
Phase 0 (scaffold + toolchain + CI) — base for all phases
  └─► Phase 1 (data layer: migrations)
       └─► Phase 2 (cross-cutting backend: Shared / Settings / Auth / errors / i18n)
            ├─► Phase 3 (frontend foundation) — base for all feature UI in Phases 4–12
            ├─► Phase 4 (Portfolio) ──► Phase 10 (Resume — needs about data)
            │                            └─► Phase 9 (public pages, shared w/ 6/7/8)
            ├─► Phase 5 (TechSkills) ──► Phase 6 (Experience)
            │                            └─► Phase 7 (Projects) ──► Phase 8 (Blog)
            │                                  └─► Phase 9 (public pages)
            ├─► Phase 11 (Analytics — needs content from 4/7/8)
            └─► Phase 12 (Backup — needs all content data)
Phase 13 (Ansible + E2E + performance + final verification) — depends on all phases
```

---

## Self-check

- **Requirement coverage:** every spec file maps to steps — architecture/tech-stack (0.x), database (1.x), routes/middleware (2.3–2.5, 2.8 + feature steps), content-management per feature (4–10), authentication (2.3, 3.6), analytics (11.x), i18n/theming (2.6, 3.2, 3.3), accessibility (3.3/3.4 component contracts + B19 keyboard shortcuts, 5.2/12.4 keyboard alternatives, 13.2 axe), error-handling (2.5, 3.2), seo (2.4 sitemap/robots, 3.5, 9.x, 13.3), openapi (0.2, 2.7, 13.4), testing (per-step suites + B21 factories + 13.2 + 13.4), backup (12.x, B17/B18), devops (0.4/0.5/13.1), secrets-management (13.1). No silent omissions found — verified in 13.8 against the §6 traceability table.
- **Dependency order:** each phase depends only on earlier phases (data → backend → UI; TechSkills before Projects/Experience/Blog; Settings/Auth before content features; Resume/Analytics/Backup after their data sources; landing page after Projects public read).
- **Acceptance criteria:** every step has exactly one runnable check (test command, build command, or container/health verification) — no "looks correct" criteria.
- **Assumptions vs spec:** all assumptions (A/B log) fill gaps or are user-approved; none override a stated requirement (verified against A1–A11/B1–B37 and the spec text).

---

## 6. Traceability — Spec file coverage

| Spec file | Covered by |
|---|---|
| `architecture.md` | Phases 0, 2.4–2.5, 3, 13.3 |
| `tech-stack.md` | Phases 0.1–0.3 |
| `database.md` | Phase 1; FTS closure in 8.1 |
| `routes.md` | Phases 2.3–2.8, 4–12 |
| `public-section-ui.md` | Phases 3.3, 9.x |
| `public-section-components.md` | Phases 3.3, 3.5 (SortableList), 9.x |
| `admin-section-ui.md` | Phases 2.8 (pagination backend), 3.4, 4.2–4.3, 5.2, 6.2, 7.2, 8.3, 10.2, 11.2, 12.4 |
| `admin-section-components.md` | Phases 3.4, 3.5 (SortableList) |
| `authentication.md` | Phases 2.3, 3.6 |
| `analytics.md` | Phases 3.2 (`useAnalytics`), 11.x |
| `content-management.md` | Phases 4–8; orphan cleanup in 2.4/13.3 |
| `i18n-and-theming.md` | Phases 2.6, 3.2–3.3, 13.7 |
| `seo.md` | Phases 2.4 (sitemap/robots), 3.5, 9.x, 13.3 |
| `accessibility.md` | Phases 3.3–3.4 (component contracts), B19, 5.2 (keyboard alternatives), 13.2, 13.6 |
| `error-handling.md` | Phases 2.4 (logging/redaction), 2.5 |
| `testing.md` | Per-step suites, B21, Phases 13.2, 13.4 |
| `openapi.md` | Phases 0.2, 2.7, 13.4 |
| `backup.md` | Phase 12.x, B17/B18 |
| `devops.md` | Phases 0.4–0.5, 13.1, 13.5 |
| `secrets-management.md` | Phase 13.1 |
| `INDEX.md` | Entry point only — no requirements |
