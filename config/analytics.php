<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Event retention
    |--------------------------------------------------------------------------
    |
    | Raw `analytics_events` are kept for 90 days from event creation (see
    | analytics.md). Note (B37): the 90-day cutoff is a fixed constant in
    | the pruning pipeline (`PruneEventsUseCase`) — this key is
    | informational only.
    |
    */

    'event_retention_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Bot User-Agent blocklist
    |--------------------------------------------------------------------------
    |
    | Single regex using `|` alternation, matched case-insensitively via
    | `preg_match()`. Batches with a missing or matching User-Agent are
    | silently discarded (204) before any event is inserted (see analytics.md).
    |
    */

    'not_bot_user_agent_pattern' => env(
        'NOT_BOT_USER_AGENT_PATTERN',
        '/(Googlebot|Bingbot|GPTBot|Claude-Web|ClaudeBot|Baiduspider|YandexBot|DuckDuckBot|Slurp|Sogou|Exabot|facebot|ia_archiver|AhrefsBot|SemrushBot|MJ12bot|Dotbot|PetalBot|Bytespider|Applebot|Twitterbot|LinkedInBot|facebookexternalhit)/i'
    ) ?: '/(Googlebot|Bingbot|GPTBot|Claude-Web|ClaudeBot|Baiduspider|YandexBot|DuckDuckBot|Slurp|Sogou|Exabot|facebot|ia_archiver|AhrefsBot|SemrushBot|MJ12bot|Dotbot|PetalBot|Bytespider|Applebot|Twitterbot|LinkedInBot|facebookexternalhit)/i',

    /*
    |--------------------------------------------------------------------------
    | IP hash key
    |--------------------------------------------------------------------------
    |
    | Key for HMAC-SHA256 anonymization of IP addresses (and the feedback /
    | article-reaction fingerprints). Defaults to APP_KEY; overridable via
    | `ANALYTICS_IP_HASH_KEY` (must differ from APP_KEY per environment).
    |
    */

    'ip_hash_key' => env('ANALYTICS_IP_HASH_KEY', config('app.key')),

];
