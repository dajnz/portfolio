<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| IPs (comma-separated, or `*` for "the immediate caller") of reverse
| proxies whose forwarded headers Laravel accepts — read at request time
| by the framework's `TrustProxies` middleware (this file name mirrors the
| legacy `fideloper/proxy` package it still falls back to).
|
| TLS dev mode runs behind Traefik (nginx sees plain HTTP), so `.env` sets
| `TRUSTED_PROXIES=*` there: `X-Forwarded-Proto: https` (forwarded to
| PHP-FPM by nginx) makes Laravel generate https:// asset/route URLs.
| Localhost mode and production terminate TLS at nginx itself and leave
| the variable empty — spoofable forwarded headers are then ignored.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES'),

];
