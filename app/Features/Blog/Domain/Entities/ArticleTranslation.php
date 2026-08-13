<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Entities;

use InvalidArgumentException;

/**
 * A per-locale article translation row (database.md:351-364): locale,
 * title, short description and Markdown content — all mandatory.
 * `reading_time_minutes` is carried for the round-trip (recomputed on
 * every save from the content — {@see \App\Features\Blog\Domain\ValueObjects\ReadingTime}).
 *
 * Value object: immutable, no identity — the `(article_id, locale)`
 * pair identifies the row.
 */
final readonly class ArticleTranslation
{
    public function __construct(
        private string $locale,
        private string $title,
        private string $shortDescription,
        private string $content,
        private int $readingTimeMinutes = 0,
    ) {
        if ($locale === '') {
            throw new InvalidArgumentException('Article translation locale must not be empty.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('Article translation title must not be empty.');
        }

        if ($shortDescription === '') {
            throw new InvalidArgumentException('Article translation short description must not be empty.');
        }

        if ($content === '') {
            throw new InvalidArgumentException('Article translation content must not be empty.');
        }
    }

    public function toLocale(): string
    {
        return $this->locale;
    }

    public function toTitle(): string
    {
        return $this->title;
    }

    public function toShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function toContent(): string
    {
        return $this->content;
    }

    public function toReadingTimeMinutes(): int
    {
        return $this->readingTimeMinutes;
    }
}
