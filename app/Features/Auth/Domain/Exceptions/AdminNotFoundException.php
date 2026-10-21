<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Exceptions;

use DomainException;

/**
 * The admin does not exist — surfaced as a redirect/404 by the controllers.
 */
final class AdminNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Admin [{$id}] not found.");
    }

    public static function forLoginOrEmail(?string $loginOrEmail): self
    {
        return new self($loginOrEmail === null
            ? 'No admin account found.'
            : "No admin account found for '{$loginOrEmail}'.");
    }
}
