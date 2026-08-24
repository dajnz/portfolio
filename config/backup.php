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
    | Upload size limit
    |--------------------------------------------------------------------------
    |
    | Fallback (in KB) for the `backup_upload_max_size` setting — the
    | DR upload form's file size limit (database.md:57, default 100 MB).
    | The DB `settings` row wins at runtime.
    |
    */

    'upload_max_size_kb' => 102400,

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

    /*
    |--------------------------------------------------------------------------
    | Included tables
    |--------------------------------------------------------------------------
    |
    | The archive's `data/` contents (backup.md:9-45): every portfolio
    | table the admin creates/manages. `resume_config` +
    | `resume_config_translations` are included on top of the spec's
    | literal listing — the resume layout/about texts/custom PDF path are
    | admin-created portfolio content and the resume PDF lives in the
    | `about/` assets (plan assumption B303). The excluded tables above
    | are never archived.
    |
    */

    'tables' => [
        'about_info',
        'about_info_translations',
        'skill_groups',
        'skill_group_translations',
        'skills',
        'contacts',
        'contact_translations',
        'projects',
        'project_translations',
        'project_tech_tags',
        'project_screenshots',
        'project_detail_items',
        'project_detail_item_translations',
        'experience_timeline_items',
        'experience_timeline_item_translations',
        'experience_item_tech_tags',
        'blog_articles',
        'blog_article_translations',
        'article_tags',
        'article_tag_translations',
        'article_tag_pivot',
        'article_skill_pivot',
        'article_reactions',
        'article_views',
        'related_articles',
        'old_slugs',
        'settings',
        'feedback',
        'resume_config',
        'resume_config_translations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Export key columns
    |--------------------------------------------------------------------------
    |
    | The keyset-pagination columns of the export reader per table
    | (backup.md:379): `id` by default; the `settings` table's key is
    | `key` (B301) and the translation/pivot tables that have no `id`
    | column use their composite primary key — the reader's row-value
    | comparison keeps the keyset stable under duplicate first-column
    | values.
    |
    */

    'table_keys' => [
        'settings' => 'key',
        'article_views' => 'article_id',
        'about_info_translations' => ['about_info_id', 'locale'],
        'skill_group_translations' => ['skill_group_id', 'locale'],
        'contact_translations' => ['contact_id', 'locale'],
        'project_translations' => ['project_id', 'locale'],
        'project_detail_item_translations' => ['detail_item_id', 'locale'],
        'experience_timeline_item_translations' => ['item_id', 'locale'],
        'experience_item_tech_tags' => ['experience_item_id', 'skill_id'],
        'blog_article_translations' => ['article_id', 'locale'],
        'article_tag_translations' => ['article_tag_id', 'locale'],
        'article_tag_pivot' => ['article_id', 'tag_id'],
        'article_skill_pivot' => ['article_id', 'skill_id'],
        'related_articles' => ['article_id', 'related_article_id'],
        'project_tech_tags' => ['project_id', 'skill_id'],
        'resume_config_translations' => ['resume_config_id', 'locale'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Restore truncate order
    |--------------------------------------------------------------------------
    |
    | Child/referencing tables first, then parents (backup.md:401) —
    | the reverse of the database.md migration order. `feature_flags` is
    | never truncated (B17); the analytics tables are wiped (truncated)
    | during restore per backup.md:401 — including `analytics_daily_metrics`
    | (plan assumption B302: the 11.3 rework's per-day metrics would
    | otherwise survive the wipe and mix stale pre-restore numbers into
    | the rebuilt reports).
    |
    */

    'truncate_order' => [
        'resume_config_translations',
        'resume_config',
        'analytics_events',
        'analytics_daily_metrics',
        'analytics_reports',
        'feedback',
        'experience_item_tech_tags',
        'experience_timeline_item_translations',
        'experience_timeline_items',
        'old_slugs',
        'project_screenshots',
        'project_tech_tags',
        'project_detail_item_translations',
        'project_detail_items',
        'project_translations',
        'projects',
        'related_articles',
        'article_views',
        'article_reactions',
        'article_skill_pivot',
        'article_tag_pivot',
        'article_tag_translations',
        'article_tags',
        'blog_article_translations',
        'blog_articles',
        'contact_translations',
        'contacts',
        'skills',
        'skill_group_translations',
        'skill_groups',
        'about_info_translations',
        'about_info',
        'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Staging path
    |--------------------------------------------------------------------------
    |
    | All local staging lives under `storage/app/backups/tmp/` — a fresh
    | subdirectory per run, deleted on completion or failure (backup.md:391).
    | Local-only: never scanned by `app:cleanup-orphaned-files` (which
    | inspects RustFS objects) and never included in backup contents.
    |
    */

    'staging_path' => storage_path('app/backups/tmp'),

];
