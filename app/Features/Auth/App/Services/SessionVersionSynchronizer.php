<?php

declare(strict_types=1);

namespace App\Features\Auth\App\Services;

use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use Illuminate\Support\Facades\Auth;

/**
 * App-layer service: increments `admins.session_version` and keeps the
 * current session in sync.
 *
 * Sensitive actions (password change, GitHub link/unlink, TOTP toggles)
 * bump the version to force-logout all OTHER sessions; the current session
 * is re-stored under the new version so it survives (authentication.md:158).
 *
 * `bumpAndReauthenticate()` additionally re-authenticates the current
 * session via `Auth::loginUsingId()` — required after password changes and
 * TOTP toggles. Two ordering rules apply there:
 *  1. the increment runs BEFORE the re-authentication, so the guard's
 *     cached user carries the new version (the `auth.session_version`
 *     middleware compares against `Auth::user()->session_version`);
 *  2. `Auth::loginUsingId()` regenerates the session ID and wipes its
 *     data, so the version is stored into the fresh session AFTER the
 *     re-authentication.
 */
final class SessionVersionSynchronizer
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
    ) {
    }

    /** Increments the version and stores it into the current session. */
    public function bumpAndSync(int $adminId): void
    {
        $version = $this->repository->incrementSessionVersion($adminId);

        session(['session_version' => $version]);
    }

    /** Bumps the version and re-authenticates the current session under it. */
    public function bumpAndReauthenticate(int $adminId): void
    {
        $version = $this->repository->incrementSessionVersion($adminId);

        Auth::loginUsingId($adminId);
        session(['session_version' => $version]);
    }
}
