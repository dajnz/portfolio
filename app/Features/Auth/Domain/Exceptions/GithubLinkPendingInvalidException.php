<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Exceptions;

use DomainException;

/** The pending GitHub-link state is missing, expired or references another admin. */
final class GithubLinkPendingInvalidException extends DomainException
{
}
