<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\Exceptions;

use DomainException;

/**
 * The encryption key does not exist — surfaced as a 404 by the
 * controllers.
 */
final class EncryptionKeyNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Encryption key [{$id}] not found.");
    }
}
