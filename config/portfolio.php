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
    |
    */

    'max_upload_size_kb' => 3072,

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
    | Icon system
    |--------------------------------------------------------------------------
    |
    | Configuration for the AdminIconPicker (see content-management.md).
    | `brands` and `ui` render the full Iconify set; `engineering` is a
    | curated subset used by the project detail items picker and validated
    | server-side against `icons.sets.engineering.icons`.
    |
    */

    'icons' => [
        'sets' => [
            'brands' => [
                'prefix' => 'simple-icons',
                'label' => 'Brand Icons',
                'description' => 'Social platforms, messengers, services — for contacts',
            ],
            'engineering' => [
                'prefix' => 'heroicons',
                'label' => 'Engineering Icons',
                'description' => 'Technical categories — for project detail items',
                'icons' => [
                    'heroicons:academic-cap' => 'Learning / Education',
                    'heroicons:archive-box' => 'Architecture',
                    'heroicons:bolt' => 'Performance',
                    'heroicons:chart-bar' => 'Analytics',
                    'heroicons:check-badge' => 'Quality',
                    'heroicons:circle-stack' => 'Database',
                    'heroicons:cloud' => 'Cloud / Deployment',
                    'heroicons:code-bracket' => 'Development',
                    'heroicons:cog-8-tooth' => 'Configuration',
                    'heroicons:cpu-chip' => 'Hardware / Systems',
                    'heroicons:document-text' => 'Documentation',
                    'heroicons:eye' => 'Monitoring',
                    'heroicons:globe-alt' => 'Web',
                    'heroicons:key' => 'Authentication',
                    'heroicons:lock-closed' => 'Security',
                    'heroicons:puzzle-piece' => 'Integration',
                    'heroicons:rocket-launch' => 'Launch / Startup',
                    'heroicons:server' => 'Backend / Infrastructure',
                    'heroicons:shield-check' => 'Testing / QA',
                    'heroicons:sparkles' => 'Innovation',
                    'heroicons:swatch' => 'Design / UI',
                    'heroicons:wrench-screwdriver' => 'DevOps / Tooling',
                ],
            ],
            'devicon' => [
                'prefix' => 'devicon',
                'label' => 'Tech Stack Icons',
                'description' => 'Languages, frameworks, dev tools — for skills',
            ],
            'ui' => [
                'prefix' => 'heroicons',
                'label' => 'UI Icons',
                'description' => 'General purpose — for skill groups, skills, admin navigation',
            ],
        ],
    ],

];
