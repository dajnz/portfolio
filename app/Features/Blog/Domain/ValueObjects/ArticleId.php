<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: an article's numeric identity (`blog_articles.id`).
 * The value 0 marks a transient (unsaved) aggregate. Immutable; the
 * non-negative-integer rule lives here, not in the consuming entity.
 */
final readonly class ArticleId
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('Article id must be a non-negative integer.');
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
