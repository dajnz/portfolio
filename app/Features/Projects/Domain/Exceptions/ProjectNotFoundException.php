<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\Exceptions;

use RuntimeException;

/**
 * The project does not exist (or is out of the requested scope) —
 * surfaced as a 404 by the consuming controller.
 */
final class ProjectNotFoundException extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("Project [{$id}] was not found.");
    }
}
