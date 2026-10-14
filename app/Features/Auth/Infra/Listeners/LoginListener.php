<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Listeners;

use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Remember-me compatibility: when a recaller cookie re-authenticates the
 * admin (session expiry + `Auth::viaRemember()`), the fresh session lacks
 * the `session_version` key. Repopulate it from the model so the
 * `auth.session_version` middleware does not force-logout immediately after
 * remember-me restoration.
 */
final class LoginListener
{
    public function handle(Login $event): void
    {
        if (! Auth::viaRemember()) {
            return;
        }

        if ($event->user instanceof AdminModel) {
            Session::put(['session_version' => (int) $event->user->session_version]);
        }
    }
}
