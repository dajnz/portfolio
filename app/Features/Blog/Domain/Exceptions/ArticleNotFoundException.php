<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Exceptions;

use DomainException;

/**
 * The article does not exist (or is not visible to the caller) —
 * surfaced as a 404 by the controllers.
 */
final class ArticleNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Article [{$id}] not found.");
    }
}
