# ADR-0018: Backup & restore architecture

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: A1, B8, B17, B18, B36, B298, B299, B301, B302, B303, B304, B305, B306
- **Supersedes:** —

## Context

The project needed full database + asset backup/restore, including admin-triggered restore and disaster-recovery import. Decisions were needed on async versus synchronous restore, the archive and encryption-key format, which tables are preserved or wiped, and keeping the application layer free of infrastructure dependencies.

## Decision

- **Async restore (A1, B8, B36):** an HTTP-triggered restore dispatches a queued `RestoreBackupJob` (mirroring `CreateBackupJob`); `backups.status` transitions `pending → processing → completed/failed` and the UI polls `GET /admin/backups/{backup}/status` (~2 s), reloading on completion. CLI `backup:restore` stays synchronous, and the progress bar is indeterminate (`null`). The endpoint returns `{ "data": { "id", "status", "progress", "completed_at", "error_message" } }`; `progress` is nullable, `error_message` only present when status is `failed`.
- **State machine (B298, B299):** `Backup::startProcessing()` accepts `Pending` and `Completed` (clearing the completion stamp re-set by `complete()`/`fail()`), giving a `completed → processing` restore transition. The entity enforces the per-status row shape: `completed` carries path/size/record-counts/assets-stats/completed_at, `failed` the completed_at, `pending`/`processing` neither. Because DB `path`/`size_bytes` are `NOT NULL`, pending rows store the placeholders `''`/`0` and `Backup::fromRow()` maps them back to `null` until `complete()`.
- **Archive & keys (B18):** the filename is `backup-{Y-m-d-His}-k{id}--{name}.zip`, embedding `backup_encryption_keys.id` for offline key identification; the `--{name}` suffix (sanitized `[A-Za-z0-9._-]`) is human-readable only — key resolution always uses the record's `encryption_key_id`.
- **Preserved vs wiped (B17, B302, B303):** `feature_flags` is in `config('backup.php')` `excluded_tables`, never archived and never truncated (environment-specific). Restore wipes `analytics_daily_metrics` too (listed in `truncate_order`) so reports start fresh; `resume_config` + `resume_config_translations` are included in `config('backup.tables')` and the truncate order.
- **Error reporting (B304):** the committed OpenAPI requires `error_message` on `failed` rows, but the table has no such column — the jobs' `failed()` hooks store `backup:error:{id}` (24 h TTL) and the status endpoint returns it only when the row is `failed`; `progress` is always `null`.
- **Archive/DB plumbing (B301, B305):** `ZipBackupArchive::create()` gained optional `?int $encryptionKeyId` and `?string $filename` params so the record, the RustFS object key and the offline artifact always agree. `LaravelDbTableBatchReader` accepts `string|array $keyColumns` (row-value `whereRowValues()` keysets) and reads per-table keys from `config('backup.table_keys')` (the `settings` table paginates by `key`); `LaravelDbTableBatchWriter` has a nullable `$upsertKey` (settings upsert by `key`); `RustFsBatchTransfer` is constructed per disk/bucket.
- **Infra-free app layer (B306):** `BackupTableFactory` builds `BackupTableExport`/`BackupTableImport` bundles from `config('backup.tables')` + `table_keys`; workflows iterate them and carry a `BackupSources` DTO. `DbTableExportable::writeToFile()` returns the row count; `StorageBatchTransferable` gained `deleteObjects()`; restore truncation goes through `TableTruncatorInterface`/`LaravelTableTruncator` (config-only `[a-z_]+` identifier whitelist) inside a dedicated `DatabaseRestoreService`; create jobs are dispatched via `BackupJobDispatcherInterface`/`QueueBackupJobDispatcher`. Cross-feature reads go only through Shared: `Shared/Domain/RepositoryInterfaces/SettingsReadRepositoryInterface` (`value(string $key, ...)`, impl `EloquentSettingsReadRepository`).

## Consequences

- Async restore re-uses the same `backups` record as create, so the extended state machine and polling semantics must be respected by any new restore entry point.
- The transient `error_message` is not durable and may expire after 24 h.
- The `-k{id}` filename suffix is identification-only; never parse it to resolve keys.
