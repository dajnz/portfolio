# ADR-0001: Modular monolith, feature slicing & layering rules

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B108, B109, B121, B181, B182, B185, B186, B219, B259, B344
- **Supersedes:** —

## Context

The application is a modular monolith organised by feature slice. Without explicit rules, framework types, cross-feature imports and duplicated UI logic leak between slices, coupling the domain to Laravel and making features hard to change independently. A durable set of layering and extraction rules was needed so each slice stays testable and framework-free at its core.

## Decision

- **Feature slices (B181):** code lives under `app/Features/{Feature}/` for `Portfolio`, `Blog`, `Projects`, `Experience`, `TechSkills`, `Auth`, `Analytics`, `Settings`, `Resume`, `Backup`, `Shared`.
- **Framework-free domain (B109, B219):** domain repository interfaces never return a Laravel `Builder`; cursor-list queries live in app-layer contracts at `{Feature}/App/Contracts/` (`ArticleListQueryRepositoryInterface`, `ProjectListQueryRepositoryInterface`, `TimelineItemListQueryRepositoryInterface`, `ResumeDataReaderInterface`, `ContactListQueryRepositoryInterface`, `FeedbackListQueryRepositoryInterface`) which the `List*UseCases` depend on. Their framework query-builder methods return `mixed` (not an Eloquent builder), consumed through the shared `AdminListPaginatorInterface` callable.
- **Use-case-orchestrated controllers (B181, B259):** controllers never access persistence or orchestrate domain operations themselves — no repositories, Eloquent models, direct DB/storage access; every read/write, including a single-method operation, delegates to an app use case. Domain entities, value objects, enums and exceptions may still be used for request preparation and prop/error mapping. Infrastructure-only endpoints (the health probe) and response streaming are explicit transport exceptions. `PublicExperienceController` runs no queries; `GetPublicTimelineUseCase` returns `has_projects` + `about_name`.
- **Use-case-orchestrated commands and jobs (ADR-0033):** console commands and queued jobs are thin shells too — they must not import repositories, Eloquent models, Infra repositories or other jobs, and must not call `DB`/`Storage`/`Bus`/`Config` directly; every action delegates to an app use case. Build tooling (`lang:export`) is the documented exception.
- **Controllers own transport shape (B185):** controllers prepare Inertia/JSON output (snake_case keys, date formatting, per-locale resolution); app-layer DTOs are not wire shapes.
- **App layer never imports Infra (B182, B186, B219, B344):** abstractions live in `Shared/App/Contracts/` — `PreviewUrlGeneratorInterface` (impl `LaravelPreviewUrlGenerator` over `URL::temporarySignedRoute`), `CountCacheInvalidatorInterface` (`AdminListCountCacheInvalidator`), `AdminListPaginatorInterface` (`AdminListPaginator`), `AssetCleanerInterface` (`RustFsAssetCleaner`), `MarkdownContentPipelineInterface` (`MarkdownContentPipeline`, singleton) and `LoggerInterface` (`LaravelLogger` wrapping `LogManager`); all bound in `AppServiceProvider`.
- **Logger contract (B344):** the app layer must not use the `Log` facade; it injects `LoggerInterface` (`TrackEventUseCase`, `BackupWorkflowService`, `RestoreWorkflowService`, Auth `LoginUseCase`/`LoginSessionService`/`TotpVerifier`).
- **Use-case composition (ADR-0032):** use cases never depend on other use cases; behaviour shared by several entry points lives in `{Feature}/App/Services/`. App services never depend on use cases either.
- **Per-list paginator configuration (B182):** `AdminListPaginatorInterface` keeps a single signature (the per-list sort columns are not part of it). The default binding allows no sortable columns; a list that allows sorting supplies its own columns and default through a Laravel contextual binding — `$this->app->when(ListTimelineItemsUseCase::class)->needs(AdminListPaginatorInterface::class)->give(fn () => new AdminListPaginator(allowedSortColumns: ['started_at'], defaultSortColumn: 'started_at'))` (Contacts likewise for `sort_order`). The app-layer port and its consumers stay unaware of the wiring.
- **DTO-free repositories / DB-only models (B186):** repositories take and return primitives and domain VOs only; use cases convert to DTOs and own asset orchestration (obsolete paths from `UpdateProjectUseCase`, force-delete paths from `ProjectRepositoryInterface::assetPathsOf`). Methods are split into semantic helpers ≤ 35 lines.
- **Use-case boundaries (B181, B185):** use cases accept and return app-layer DTOs; entities stay inside the app layer (mapped via DTO factories).
- **Shared extraction (B108, B121):** reusable domain types are extracted, not duplicated — `StoredPicture` (`Shared/Domain/ValueObjects/`) serves Portfolio, Projects and Blog image fields; `AboutInfoTranslation`/`ContactTranslation` are classified as VOs; UI presentation is not a domain concern (`AvailabilityStatus::toVariant()` removed). Shared surface logic (`useAdminMenu`, `useJsonApi`, `cursorNavigationParams`, `useReorder`) lives under `Shared/CrossSurface/`.
- **`useJsonApi` (B121):** the user-approved fetch wrapper (over axios; call surface ~6 sites, no dependency) sets the XSRF-TOKEN cookie header (CSRF) + `Accept: application/json`, supports JSON or FormData bodies (FormData skips `Content-Type` so the boundary is auto-set), passes the abort signal through and throws on non-OK.

## Consequences

- New shared behaviour requires an interface + Infra adapter + `AppServiceProvider` binding; features never import each other's classes.
- Ad-hoc `fetch` in admin pages is replaced by `useJsonApi`; only `useAnalytics`' keepalive POST is intentionally left untouched.
- Thin controllers keep use-case tests as the primary logic coverage.
- Moving a type to `Shared` is the sanctioned fix for cross-feature reuse; duplicating a shared module in a slice is a regression.
