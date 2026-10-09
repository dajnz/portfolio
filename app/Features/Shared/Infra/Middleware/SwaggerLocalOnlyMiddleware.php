<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `swagger.local` middleware alias — the Swagger UI routes are only served
 * in the `local` environment. The routes are registered unconditionally by
 * l5-swagger; this gate makes them behave as if they did not exist outside
 * local.
 */
final class SwaggerLocalOnlyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment('local'), 404);

        return $next($request);
    }
}
