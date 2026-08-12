<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a `blog_articles` row reference (e.g. a project's
 * `tech_writeup_article_id`). The reference is application-level — no
 * FK (database.md:201) — but the value itself is typed so domain
 * aggregates don't depend on raw integers. Immutable.
 */
final readonly class BlogArticleId
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 1) {
            throw new InvalidArgumentException('Blog article id must be a positive integer.');
        }
    }

    public function toValue(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }
}
