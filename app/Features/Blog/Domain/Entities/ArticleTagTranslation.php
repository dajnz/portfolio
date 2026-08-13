<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Entities;

use InvalidArgumentException;

/**
 * A per-locale article-tag name (database.md:377-384): locale + name,
 * name mandatory and limited to 100 chars. Value object: immutable,
 * no identity — the `(article_tag_id, locale)` pair identifies the row.
 */
final readonly class ArticleTagTranslation
{
    public function __construct(
        private string $locale,
        private string $name,
    ) {
        if ($locale === '') {
            throw new InvalidArgumentException('Article tag translation locale must not be empty.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Article tag translation name must not be empty.');
        }

        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException('Article tag translation name must not exceed 100 characters.');
        }
    }

    public function toLocale(): string
    {
        return $this->locale;
    }

    public function toName(): string
    {
        return $this->name;
    }
}
