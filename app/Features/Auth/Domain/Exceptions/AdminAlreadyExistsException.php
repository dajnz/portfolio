<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Exceptions;

use DomainException;

/**
 * The single-admin invariant already has an account and `--force` was not
 * given.
 */
final class AdminAlreadyExistsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('An admin account already exists.');
    }
}
