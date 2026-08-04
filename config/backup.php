<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | `encryption_enabled` (default true) skips encryption entirely when
    | false — an emergency escape hatch. `encryption_key` is the legacy
    | fallback only: backups with `encryption_key_id = NULL` (created before
    | encryption was introduced) use it as the zip password for restore.
    | New backups use keys from the `backup_encryption_keys` table.
    |
    */

    'encryption_enabled' => (bool) env('BACKUP_ENCRYPTION_ENABLED', true),
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Fallback for the `backup_max_count` setting (the DB `settings` row wins
    | at runtime). Oldest backups are pruned after a successful backup when
    | the count exceeds this value.
    |
    */

    'max_count' => (int) env('BACKUP_MAX_COUNT', 30),

    /*
    |--------------------------------------------------------------------------
    | Excluded tables
    |--------------------------------------------------------------------------
    |
    | Never archived and never truncated during restore (see backup.md).
    | `feature_flags` and `admins` are environment-specific; analytics events
    | and reports are volatile aggregates.
    |
    */

    'excluded_tables' => ['admins', 'analytics_events', 'analytics_reports', 'feature_flags'],

];
