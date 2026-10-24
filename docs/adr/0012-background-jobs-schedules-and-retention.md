# ADR-0012: Background jobs, schedules & retention

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B22, B37, B66
- **Supersedes:** —
- **Partially superseded by:** ADR-0017 (analytics cadence)

## Context

The application relies on recurring maintenance, analytics and backup jobs whose cadences and retention windows were not fixed by the spec. A stable schedule and a single owner for the retention value were needed so that operators and future code agree on when data is generated and pruned.

## Decision

- Scheduled command cadences are fixed:
  - `app:cleanup-orphaned-files` and `app:prune-trashed-items` — monthly, `0 3 1 * *`.
  - `analytics:generate-reports` — every 4h, `0 */4 * * *`. **Superseded by ADR-0017:** replaced by the daily `analytics:generate-daily-metrics` (`0 2 * * *`); `analytics:generate-reports` remains only as a manual full rebuild and is not scheduled.
  - `analytics:prune-events` — monthly, `0 3 1 * *`.
  - `backup:create` — daily, `0 2 * * *`.
- Analytics 90-day retention is a fixed constant inside `AggregateAnalyticsReportsJob`; the `config/analytics.php` retention key is informational only.
- `app:prune-trashed-items` and `app:cleanup-orphaned-files` keep their query-builder behavior, now inside `PruneTrashedItemsUseCase` / `CleanupOrphanedFilesUseCase` and their Infra adapters (`TrashedItemPrunerInterface`, `AssetPathProviderInterface`) as required by ADR-0033: chunked force-delete of rows past `trash_retention_days`, and orphan detection over the 6 reference columns plus Markdown/`<img>` path extraction.
- Per-model `forceDelete()` RustFS file cleanup and generated-resume-PDF hash matching (`about/resume_{locale}_{content_hash}.pdf`) land with the feature models (6.1/7.1/8.1) and the Resume feature (10.1), which upgrade the maintenance commands.

## Consequences

- Changing a cadence means editing the scheduler, not a config value; keep it aligned with `backup.md`.
- Changing analytics retention means editing the job constant, not `config/analytics.php`.
- Until the feature-model upgrades land, orphan cleanup is DB-only — orphaned RustFS files and stale generated PDFs are not reclaimed by the initial commands.
