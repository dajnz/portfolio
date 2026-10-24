# ADR-0019: Backup admin UI & disaster-recovery import

- **Status:** Accepted
- **Date:** 2026-10-09
- **Source:** archived implementation plan (§2), IDs: B307, B309, B311, B312, B313
- **Supersedes:** —

## Context

The admin backup page handles list, create, restore, encryption-key management and disaster-recovery (DR) upload. Decisions were needed on how async completion is detected, how completion is surfaced to the user, how DR import reconciles keys, and how to keep the complex page maintainable.

## Decision

- **Per-field errors (B307):** `RestoreForm`/`UploadRestoreForm` gain `password_error` (and the DR form `key_error`) so Inertia validation failures land on the right inputs; the restore `current_password` and DR archive errors stay in their existing slots. The DR dropzone cap uses the contract default 102400 KB (`UploadRestoreRequest` enforces the real max server-side; the client check is a UX nicety).
- **Polling (`useBackupStatusPolling`, B307, B309):** create mode polls plain `pending → processing → completed/failed`; restore mode accepts a terminal `completed` only after a non-terminal status was observed, also treats a `completed` whose `completed_at` changed from the first observation as the end, and treats a 404 as completed (the restored DB swapped the row out). Transient errors keep polling up to a ~150-tick (~5 min) cap; the sync-queue dev case ends silently at the cap.
- **Filenames (B309):** the backup/import filename carries the key name (`backup-{Y-m-d-His}-k{id}--{name}.zip`, `backup-import-...-k{id}--{name}.zip`) so an offline-saved key entry maps back to its archive without consulting the DB; `BackupFilename::for()/forImport()` take the sanitized name. Key resolution still uses `encryption_key_id`.
- **Toasts & completion (B311):** create flashes "Backup creation started." at submit and "Backup created." in the reload's `onSuccess` (a toast shown before a reload is cleared by the visit-start handler). Completion no longer reloads — the re-completed row is patched in place via `markRowCompleted` so the success toast survives. The DR upload falls back to polling the newest `backup-import-*` row; DR success clears the form via a `restore_success_tick` prop, while failure keeps the inputs for retry.
- **Key naming (B307, B311):** imported keys are named `disaster-key-{8-hex sha256 prefix of the raw value}` (deterministic, so the archive's `--{name}` suffix stays stable). UI-generated names use `nextKeyName()`: `initial-key` when no keys exist, else `rotated-{year}` with `-2`/`-3` suffixes on collision; the `encryption_key_prompt` flag auto-opens the generation confirmation dialog.
- **Client behaviours (B309, B311):** `useJsonApi` returns `undefined` for 204/205 empty bodies (encryption-key delete). Key counts reload on the `props.backups` watch (same-component visits) and key-delete eligibility counts soft-deleted backups. `CurrentPasswordRule` fails with the translated `auth.current_password_incorrect` message.
- **Structure (B312, B313):** `Pages/BackupListPage/BackupListPage.vue` is a pure orchestrator; logic lives in `useBackupList`, `useEncryptionKeys`, `useBackupOperations` and formatting in `useBackupFormat.ts` (`formatBackupDate`). Poll kinds use the `DISASTER_RECOVERY_RESTORE` constant (no `dr` abbreviations) with semantic computeds (`restoreInProgress`, `disasterRestoreErrorMessage`, ...). Imports inside the directory use the aliases `@backup-admin/*` and `@backup/Admin/*`.

## Consequences

- Any new async backup entry point must reuse the polling state machine; naive `completed` detection produces false success toasts.
- Deterministic DR key naming couples the key name to the raw value's hash.
- The BackupListPage directory forbids `../../` relative imports in favour of the feature aliases.
