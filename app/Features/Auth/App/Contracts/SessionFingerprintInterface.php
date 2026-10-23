<?php

declare(strict_types=1);

namespace App\Features\Auth\App\Contracts;

/**
 * The client fingerprint bound to an authenticated admin session: a keyed
 * HMAC over the request's user agent and IP network, so the raw values are
 * never stored. The framework-independent implementation lives on the infra
 * layer.
 */
interface SessionFingerprintInterface
{
    public function for(string $ip, ?string $userAgent): string;
}
