<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `theme` middleware alias — reads the `theme` cookie (i18n-and-theming.md:175)
 * and exposes it as a request attribute + Inertia shared prop. Values other
 * than `dark` resolve to `light` (the server cannot know the visitor's
 * `prefers-color-scheme`; the root Blade template blocks the FOUC for first
 * visits and seeds the cookie). Admin Tailwind uses `darkMode: 'class'`.
 */
final class ThemeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $theme = $request->cookie('theme') === 'dark' ? 'dark' : 'light';
        $request->attributes->set('theme', $theme);

        return $next($request);
    }
}
