<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Exceptions;

use DomainException;

/** TotpEnrollmentExpiredException: domain failure surfaced as a validation/flash error by the controller. */
final class TotpEnrollmentExpiredException extends DomainException
{
}
