<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Exceptions;

use RuntimeException;

/**
 * The DR import validation failure (backup.md:415,597): the archive
 * cannot be opened with the given key or is corrupt, or the path is
 * not a readable `.zip`. The controller maps it to the
 * "invalid key or corrupted archive" 422.
 */
final class ImportValidationException extends RuntimeException
{
}
