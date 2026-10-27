<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pagination defaults
    |--------------------------------------------------------------------------
    |
    | Default per-page counts. Overridable at runtime by the admin via the
    | `settings` table (`SettingsService` reads DB with these as fallback).
    |
    */

    'items_per_page' => (int) env('PORTFOLIO_ITEMS_PER_PAGE', 12),
    'projects_per_page' => (int) env('PORTFOLIO_PROJECTS_PER_PAGE', 12),
    'articles_per_page' => (int) env('PORTFOLIO_ARTICLES_PER_PAGE', 10),
    'experience_items_per_page' => (int) env('PORTFOLIO_EXPERIENCE_ITEMS_PER_PAGE', 15),

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    |
    | Images accept 3072 KB; PDFs (resume upload) accept 5120 KB — enforced
    | per `entity_type` by the upload endpoint (see content-management.md).
    | `image_max_dimension` is the runtime-default cap (px) for the largest
    | side of uploaded images after the WebP processing pipeline; it is
    | overridable per-install via the `settings` table.
    |
    */

    'max_upload_size_kb' => 3072,
    'image_max_dimension' => 2048,

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    */

    'supported_locales' => ['en', 'ru'],

    /*
    |--------------------------------------------------------------------------
    | Full-text search
    |--------------------------------------------------------------------------
    |
    | Locale → PostgreSQL Snowball dictionary map, read by the translation
    | model `saved` events and the search use cases. The fallback for an
    | unmapped locale is 'english'.
    |
    */

    'fts' => [
        'locales' => ['en' => 'english', 'ru' => 'russian'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Public page cache
    |--------------------------------------------------------------------------
    |
    | Caches the assembled props of anonymous public pages in the application
    | cache (Redis). Disabled by default — local development and tests render
    | every time; the deployment env files turn it on. Any successful admin
    | mutation bumps the cache version, so edits are visible immediately.
    |
    */

    'public_cache' => [
        'enabled' => (bool) env('PUBLIC_PAGE_CACHE_ENABLED', false),
        'ttl' => (int) env('PUBLIC_PAGE_CACHE_TTL', 300),
    ],

];
