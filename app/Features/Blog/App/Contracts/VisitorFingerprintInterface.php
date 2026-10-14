<?php

declare(strict_types=1);

namespace App\Features\Blog\App\Contracts;

/**
 * The keyed-HMAC visitor fingerprint (`hash_hmac('sha256',
 * ip.'|'.user_agent, key)`) that anonymizes a visitor without storing the
 * raw IP — the reaction identity and the view-counter dedup key. An
 * abstraction over the analytics hash key so the transport layer never
 * reads framework configuration.
 */
interface VisitorFingerprintInterface
{
    public function for(string $ip, ?string $userAgent): string;
}
