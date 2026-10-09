<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

/**
 * Holds the per-request CSP nonce. Registered as an app singleton by
 * `CspNonceMiddleware`; Blade templates and `HandleInertiaRequests` read it
 * via `app(CspNonce::class)->getValue()`. The default empty value is the
 * fallback when the middleware was skipped (APP_ENV=local / api routes).
 */
final class CspNonce
{
    public function __construct(
        private readonly string $value = '',
    ) {
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
