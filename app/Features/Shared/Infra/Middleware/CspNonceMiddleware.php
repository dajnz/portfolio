<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `csp` middleware alias — generates the per-request CSP nonce and appends
 * the Content-Security-Policy header (devops.md:751-813). Skipped on `/api/*`
 * (no HTML responses) and when APP_ENV=local. The nonce is stored in a
 * `CspNonce` singleton so the root Blade layout and Inertia shared props
 * can inject it into inline scripts.
 */
final class CspNonceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*') || app()->environment('local')) {
            return $next($request);
        }

        $nonce = base64_encode(random_bytes(18));
        app()->instance(CspNonce::class, new CspNonce($nonce));

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->buildPolicy($nonce));

        return $response;
    }

    private function buildPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
        ]);
    }
}
