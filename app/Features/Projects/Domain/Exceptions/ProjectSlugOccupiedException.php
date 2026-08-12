<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\Exceptions;

use RuntimeException;

/**
 * The project's slug is occupied by another non-trashed project — the
 * restore uniqueness re-check (B29, content-management.md:901).
 * Surfaced as a validation error on the `slug` field by the controller.
 */
final class ProjectSlugOccupiedException extends RuntimeException
{
    public static function forSlug(string $slug): self
    {
        return new self(
            "The project slug [{$slug}] is already taken."
        );
    }
}
