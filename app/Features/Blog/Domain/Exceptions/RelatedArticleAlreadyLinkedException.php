<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Exceptions;

use DomainException;

/**
 * The related-article pair already exists — surfaced as a 409 by the
 * attach route (content-management.md:252).
 */
final class RelatedArticleAlreadyLinkedException extends DomainException
{
    public static function forPair(int $articleId, int $relatedArticleId): self
    {
        return new self("Article [{$relatedArticleId}] is already linked to article [{$articleId}].");
    }
}
