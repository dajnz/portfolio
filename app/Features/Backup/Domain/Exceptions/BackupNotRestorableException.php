<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\Exceptions;

use DomainException;

/**
 * The backup is not in a restorable state (only `completed` backups can
 * be restored) — surfaced as a flash error by the controller.
 */
final class BackupNotRestorableException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Backup [{$id}] is not restorable.");
    }
}
