<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use DateTimeImmutable;

/**
 * An article as a list item — the admin table rows and the admin
 * autocomplete items. The aggregate parts travel as data DTOs (the
 * unified tag chips and the related rows — each carrying the ids);
 * built by the use cases from the raw list rows; the dates are
 * converted at the boundary. Immutable.
 */
final readonly class ArticleListItemDto
{
    /**
     * @param  ArticleTagOptionDto[]  $tags  The unified tag/skill chips.
     * @param  RelatedArticleDto[]  $related  The Related column chips.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public bool $isPublished,
        public ?DateTimeImmutable $publishedAt,
        public int $readingTimeMinutes,
        public int $views,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $tags = [],
        public array $related = [],
    ) {
    }

    /**
     * @param  object  $row  The locale-resolved list projection row.
     * @param  ArticleTagOptionDto[]  $tags
     * @param  RelatedArticleDto[]  $related
     */
    public static function fromRow(object $row, array $tags = [], array $related = []): self
    {
        return new self(
            id: (int) $row->id,
            slug: $row->slug,
            title: $row->title,
            isPublished: (bool) ($row->is_published ?? false),
            publishedAt: $row->published_at === null || $row->published_at === ''
                ? null
                : new DateTimeImmutable($row->published_at),
            readingTimeMinutes: (int) ($row->reading_time_minutes ?? 0),
            views: (int) ($row->views ?? 0),
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: new DateTimeImmutable($row->updated_at),
            tags: $tags,
            related: $related,
        );
    }
}
