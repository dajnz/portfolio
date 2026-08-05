<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `trailing_slash` middleware alias (seo.md:287-297) — 301-redirects any
 * URI ending in `/` to the slashless version, preserving the query string.
 * Skips the root path `/`. Runs right after `log_context` so the redirect
 * is logged, and before any state-setting middleware (csp, maintenance,
 * locale, theme).
 */
final class TrailingSlashRedirectMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if ($path !== '/' && str_ends_with($path, '/')) {
            $target = rtrim($path, '/');
            $query = $request->getQueryString();

            return redirect($query === null ? $target : $target.'?'.$query, 301);
        }

        return $next($request);
    }
}
