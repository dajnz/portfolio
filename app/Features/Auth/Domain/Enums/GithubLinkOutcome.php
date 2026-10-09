<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Enums;

/**
 * Outcome of the GitHub OAuth link intent.
 */
enum GithubLinkOutcome
{
    /** The GitHub ID is already bound to this admin — no-op flash. */
    case AlreadyLinked;

    /** The GitHub ID is bound to a different admin — reject, no DB write. */
    case Conflict;

    /** TOTP is enabled — re-authentication required before the link completes. */
    case TotpRequired;

    /** Link persisted. */
    case Linked;
}
