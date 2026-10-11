<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\Exceptions;

use RuntimeException;

/**
 * The timeline item does not exist (or is out of the requested scope) —
 * surfaced as a 404 by the consuming controller.
 */
final class TimelineItemNotFoundException extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self("Timeline item [{$id}] was not found.");
    }
}
