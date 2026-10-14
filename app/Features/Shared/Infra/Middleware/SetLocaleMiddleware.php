<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * `locale` middleware alias — resolves the request locale: the `locale` cookie
 * wins, falling back to `$request->getPreferredLanguage()`, then the default
 * `en`. Invalid values resolve to `en`. Sets `app()->setLocale()`, stores the
 * resolved value on the request attributes (read by `HandleInertiaRequests`
 * and `SetLocaleFromPathMiddleware`), and adds the `Content-Language` response
 * header.
 */
final class SetLocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        App::setLocale($locale);
        $request->attributes->set('locale', $locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolveLocale(Request $request): string
    {
        $supported = Config::get('portfolio.supported_locales', ['en', 'ru']);
        $default = $supported[0] ?? 'en';

        $cookie = $request->cookie('locale');
        if (is_string($cookie) && in_array($cookie, $supported, true)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage($supported);

        return in_array($preferred, $supported, true) ? $preferred : $default;
    }
}
