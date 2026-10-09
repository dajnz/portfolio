<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Exceptions;

use DomainException;

/**
 * The slug is occupied by a non-trashed article — the restore flow
 * re-checks uniqueness against active rows (soft-deleted entities
 * occupy their slug); surfaced as a `slug` validation error.
 */
final class ArticleSlugOccupiedException extends DomainException
{
    public static function forSlug(string $slug): self
    {
        return new self("The slug [{$slug}] is already in use by another article.");
    }
}
