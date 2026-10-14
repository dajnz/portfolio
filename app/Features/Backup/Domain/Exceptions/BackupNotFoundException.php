<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\Exceptions;

use DomainException;

/**
 * The backup does not exist (or is soft-deleted) — surfaced as a 404 by
 * the controllers.
 */
final class BackupNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Backup [{$id}] not found.");
    }
}
