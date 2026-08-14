<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Exceptions;

use DomainException;

/**
 * A related-article target is neither already linked nor published —
 * new links must target published articles (content-management.md:687);
 * links created while the target was published are preserved even after
 * an unpublish (B230). Surfaced as a `related_articles` validation
 * error by the store/update actions.
 */
final class RelatedArticleNotLinkableException extends DomainException
{
    public static function forArticle(int $relatedArticleId): self
    {
        return new self("Article [{$relatedArticleId}] is not published and cannot be linked.");
    }

    public static function forSelf(int $articleId): self
    {
        return new self("Article [{$articleId}] cannot be linked to itself.");
    }
}
