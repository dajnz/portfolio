<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * `locale.path` middleware alias — runs on locale-prefixed routes
 * (`/en/...`, `/ru/...`) after `SetLocaleMiddleware`. The `{locale}` route
 * parameter overrides the cookie-resolved locale; the route parameter is
 * constrained to `en|ru` at route registration, so an invalid value can never
 * reach this middleware. The `Content-Language` header is rewritten to reflect
 * the actually-served locale.
 */
final class SetLocaleFromPathMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        if (is_string($locale) && in_array($locale, Config::get('portfolio.supported_locales', ['en', 'ru']), true)) {
            App::setLocale($locale);
            $request->attributes->set('locale', $locale);
        }

        $response = $next($request);
        $response->headers->set('Content-Language', (string) $request->attributes->get('locale', App::getLocale()));

        return $response;
    }
}
