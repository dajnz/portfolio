<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Exceptions;

use DomainException;

/**
 * The article tag does not exist — surfaced as a 404 by the tag
 * controllers.
 */
final class ArticleTagNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Article tag [{$id}] not found.");
    }
}
