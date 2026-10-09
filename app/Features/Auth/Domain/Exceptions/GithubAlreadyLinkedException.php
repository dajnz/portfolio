<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Exceptions;

use DomainException;

final class GithubAlreadyLinkedException extends DomainException
{
}
