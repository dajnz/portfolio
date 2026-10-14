<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Services;

use App\Features\Blog\App\Contracts\VisitorFingerprintInterface;
use Illuminate\Support\Facades\Config;

/**
 * The analytics-key-backed visitor fingerprint behind the app-layer
 * {@see VisitorFingerprintInterface} contract.
 */
final class LaravelVisitorFingerprint implements VisitorFingerprintInterface
{
    public function for(string $ip, ?string $userAgent): string
    {
        $key = Config::get('analytics.ip_hash_key') ?: Config::get('app.key');

        return hash_hmac('sha256', $ip.'|'.$userAgent, (string) $key);
    }
}
