<?php

declare(strict_types=1);

namespace App\Features\Auth\App\Services;

use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Shared\App\Contracts\AdminSessionInterface;

/**
 * Increments `admins.session_version` and keeps the current session in sync.
 *
 * Sensitive actions (password change, GitHub link/unlink, TOTP toggles)
 * bump the version to force-logout all OTHER sessions; the current session
 * is re-stored under the new version so it survives.
 *
 * `bumpAndReauthenticate()` additionally re-authenticates the current
 * session through the auth guard — required after password changes and
 * TOTP toggles. Two ordering rules apply there:
 *  1. the increment runs BEFORE the re-authentication, so the guard's
 *     cached user carries the new version (the `auth.session_version`
 *     middleware compares against the authenticated user's version);
 *  2. the guard login regenerates the session ID and wipes its
 *     data, so the version is stored into the fresh session AFTER the
 *     re-authentication.
 */
final class SessionVersionSynchronizer
{
    public function __construct(
        private readonly AdminRepositoryInterface $repository,
        private readonly AdminSessionInterface $sessions,
    ) {
    }

    public function bumpAndSync(int $adminId): void
    {
        $version = $this->repository->incrementSessionVersion($adminId);

        $this->sessions->put(['session_version' => $version]);
    }

    public function bumpAndReauthenticate(int $adminId): void
    {
        $version = $this->repository->incrementSessionVersion($adminId);

        $this->sessions->loginUsingId($adminId);
        $this->sessions->put(['session_version' => $version]);
    }
}
