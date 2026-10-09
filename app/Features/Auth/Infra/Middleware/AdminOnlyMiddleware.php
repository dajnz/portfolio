<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Middleware;

use App\Features\Auth\Infra\Services\AdminAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `admin` middleware alias: the authenticated user must be an `AdminModel`.
 * Since `admins` is the only credential table this is effectively a
 * type/table check, kept as a defensive guard against misconfiguration.
 */
final class AdminOnlyMiddleware
{
    public function __construct(
        private readonly AdminAuthService $auth,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->auth->isAuthenticatedAdmin()) {
            abort(403, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
