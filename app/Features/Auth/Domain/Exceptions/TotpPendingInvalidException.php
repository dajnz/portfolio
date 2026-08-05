<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Exceptions;

use DomainException;

/** The half-auth TOTP pending state is missing or references a deleted admin. */
final class TotpPendingInvalidException extends DomainException
{
}
