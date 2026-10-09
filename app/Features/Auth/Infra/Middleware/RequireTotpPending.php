<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Middleware;

use App\Features\Auth\Infra\Services\AdminAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `totp.pending` middleware alias: gates the login TOTP routes on the
 * half-auth state. Rejects the request when the pending key is missing,
 * expired, the admin no longer exists, or the admin is TOTP-locked —
 * clearing `totp.pending_*` and redirecting to the login page with the
 * appropriate flash message. The checks themselves are semantic methods on
 * `AdminAuthService`.
 */
final class RequireTotpPending
{
    public function __construct(
        private readonly AdminAuthService $auth,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->auth->hasPendingTotpLogin()) {
            return redirect()->route('login');
        }

        if ($this->auth->pendingTotpLoginExpired()) {
            $this->auth->clearPendingTotpLogin();

            return redirect()->route('login')->with('notification', ['type' => 'error', 'message' => __('auth.totp_verification_expired')]);
        }

        $admin = $this->auth->pendingTotpLoginAdmin();

        if ($admin === null) {
            $this->auth->clearPendingTotpLogin();

            return redirect()->route('login');
        }

        if ($this->auth->adminIsTotpLocked($admin)) {
            $this->auth->clearPendingTotpLogin();

            return redirect()->route('login')->with('notification', ['type' => 'error', 'message' => $this->auth->totpLockoutFlash($admin)]);
        }

        return $next($request);
    }
}
