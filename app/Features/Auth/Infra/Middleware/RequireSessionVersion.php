<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Middleware;

use App\Features\Auth\Infra\Services\AdminAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * `auth.session_version` middleware alias: compares the version stored in
 * the session at login against the admin's current `session_version`. A
 * mismatch means a sensitive action (password change, TOTP toggle, GitHub
 * link) happened elsewhere — this session is force-logged-out.
 */
final class RequireSessionVersion
{
    public function __construct(
        private readonly AdminAuthService $auth,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->auth->sessionVersionMatches()) {
            $this->auth->forceLogout();

            return Redirect::route('login')->with('notification', ['type' => 'error', 'message' => __('auth.session_expired')]);
        }

        return $next($request);
    }
}
