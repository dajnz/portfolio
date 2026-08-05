<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Exceptions;

use DomainException;

/** TOTP verification rejected because a lockout is (or was just) active. */
final class TotpLockedException extends DomainException
{
}
