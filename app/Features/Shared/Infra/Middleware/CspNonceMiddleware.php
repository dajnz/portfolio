<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * `csp` middleware alias — generates the per-request CSP nonce and appends
 * the Content-Security-Policy header. Registered globally so unmatched routes
 * (the 404/403 error pages) carry the policy too. Skipped on `/api/*` (no HTML
 * responses), the bare health routes (ADR-0015) and when APP_ENV=local. The
 * nonce is stored in a `CspNonce` singleton so the root Blade layout and
 * Inertia shared props can inject it into inline scripts.
 */
final class CspNonceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*', 'up', 'up/*') || App::environment('local')) {
            return $next($request);
        }

        $nonce = base64_encode(random_bytes(18));
        App::instance(CspNonce::class, new CspNonce($nonce));

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
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
        ]);
    }
}
