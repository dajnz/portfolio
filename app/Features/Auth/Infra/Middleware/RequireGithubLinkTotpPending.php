<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Middleware;

use App\Features\Auth\Infra\Services\AdminAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `totp.pending.github_link` middleware alias (authentication.md:162-177):
 * mirrors `RequireTotpPending` but keys on `github_link.pending_admin_id`.
 * Unlike the login TOTP page the admin is already fully authenticated here
 * — failures clear the `github_link.*` keys and redirect to the profile
 * page (with the lockout flash when a lock is active).
 */
final class RequireGithubLinkTotpPending
{
    public function __construct(
        private readonly AdminAuthService $auth,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Session expired mid-flow — the pending state implies an
        // authenticated admin (authentication.md:173).
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $this->auth->githubLinkPendingForCurrentAdmin()) {
            $this->auth->clearPendingGithubLink();

            return redirect()->route('profile');
        }

        if ($this->auth->pendingGithubLinkExpired()) {
            $this->auth->clearPendingGithubLink();

            return redirect()->route('profile');
        }

        $admin = $this->auth->pendingGithubLinkAdmin();

        if ($admin === null) {
            $this->auth->clearPendingGithubLink();

            return redirect()->route('profile');
        }

        if ($this->auth->adminIsTotpLocked($admin)) {
            $this->auth->clearPendingGithubLink();

            return redirect()->route('profile')->with('notification', ['type' => 'error', 'message' => $this->auth->totpLockoutFlash($admin)]);
        }

        return $next($request);
    }
}
