<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| The public API (`/api/analytics/track`, `/api/feedback`) is consumed by the
| same-origin frontend only. Restricting the allowed origin to `APP_URL`
| prevents arbitrary sites from driving those write endpoints from a
| visitor's browser. Same-origin requests are unaffected by CORS.
|
*/

$appUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['POST', 'OPTIONS'],

    'allowed_origins' => [$appUrl],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
