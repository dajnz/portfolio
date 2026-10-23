<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Listeners;

use App\Features\Auth\App\Contracts\SessionFingerprintInterface;
use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Binds every freshly authenticated admin session to the current client
 * fingerprint. On recaller-based re-authentication (session expiry +
 * `Auth::viaRemember()`) it also repopulates `session_version`, since the
 * fresh session lacks it and the `auth.session_version` middleware would
 * otherwise force-logout immediately.
 */
final class LoginListener
{
    public function __construct(
        private readonly SessionFingerprintInterface $fingerprint,
        private readonly Request $request,
    ) {
    }

    public function handle(Login $event): void
    {
        if (! $event->user instanceof AdminModel) {
            return;
        }

        Session::put([
            'auth_fingerprint' => $this->fingerprint->for(
                (string) $this->request->ip(),
                $this->request->userAgent(),
            ),
        ]);

        if (Auth::viaRemember()) {
            Session::put(['session_version' => (int) $event->user->session_version]);
        }
    }
}
