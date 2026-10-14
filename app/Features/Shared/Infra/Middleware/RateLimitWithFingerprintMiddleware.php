<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * `rate_limit.fingerprint` middleware alias — the second, IP+User-Agent layer
 * of the feedback-form protection. The throttle key is
 * `feedback|{hash_hmac(ip)}|{hash_hmac(user_agent)}` (keyed HMAC-SHA256, same
 * key as IP anonymization) — the client-provided fingerprint is deliberately
 * excluded because it is attacker-controllable. Limit: 3 attempts per 3600
 * seconds per composite key. Layer 1 is the `throttle:feedback` named limiter
 * (5/60 s/IP) applied on the route.
 */
final class RateLimitWithFingerprintMiddleware
{
    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 3600;

    public function __construct(
        private readonly RateLimiter $limiter,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->fingerprintKey($request);

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return $this->tooManyRequestsResponse($request, $key);
        }

        $this->limiter->hit($key, self::DECAY_SECONDS);

        return $next($request);
    }

    private function fingerprintKey(Request $request): string
    {
        $hashKey = Config::get('analytics.ip_hash_key', Config::get('app.key'));

        return 'feedback|'
            .hash_hmac('sha256', (string) $request->ip(), (string) $hashKey)
            .'|'
            .hash_hmac('sha256', (string) $request->userAgent(), (string) $hashKey);
    }

    private function tooManyRequestsResponse(Request $request, string $key): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return ResponseFacade::json(
            ['message' => 'Too many requests.'],
            Response::HTTP_TOO_MANY_REQUESTS,
            ['Retry-After' => $retryAfter],
        );
    }
}
