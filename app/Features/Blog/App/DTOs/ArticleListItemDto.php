<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use DateTimeImmutable;

/**
 * An article as a list item — the admin table rows and the admin
 * autocomplete items. The aggregate parts travel as data DTOs (the
 * unified tag chips); the Related column is the static link count; the
 * dates are converted at the boundary. Immutable.
 */
final readonly class ArticleListItemDto
{
    /**
     * @param  ArticleTagOptionDto[]  $tags  The unified tag/skill chips.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public bool $isPublished,
        public ?DateTimeImmutable $publishedAt,
        public int $readingTimeMinutes,
        public int $views,
        public ?string $leadImage,
        public int $relatedCount,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $tags = [],
    ) {
    }

    /**
     * @param  object  $row  The locale-resolved list projection row.
     * @param  ArticleTagOptionDto[]  $tags
     */
    public static function fromRow(object $row, array $tags = [], int $relatedCount = 0): self
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
            leadImage: ($row->lead_image ?? null) === null || $row->lead_image === ''
                ? null
                : (string) $row->lead_image,
            relatedCount: $relatedCount,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: new DateTimeImmutable($row->updated_at),
            tags: $tags,
        );
    }
}
