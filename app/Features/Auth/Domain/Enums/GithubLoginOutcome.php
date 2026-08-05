<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Enums;

/**
 * Outcome of the GitHub OAuth login intent (authentication.md:131-144).
 */
enum GithubLoginOutcome
{
    /** No admin row carries this GitHub ID — flash error and stay on login. */
    case NoAccount;

    /** Account found and TOTP is enabled — enter the half-auth state. */
    case TotpRequired;

    /** Account found, TOTP not enabled — fully authenticated. */
    case Authenticated;
}
