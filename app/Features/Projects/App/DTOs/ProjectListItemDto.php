<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\App\DTOs\ThumbnailDto;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use DateTimeImmutable;

final readonly class ProjectListItemDto
{
    /**
     * @param  SkillOptionDto[]  $techTags
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public string $briefDescription,
        public ?DateTimeImmutable $publishedAt,
        public ?string $coverImage,
        public ProjectLinksDto $links,
        public bool $isFeatured,
        public bool $isPublished,
        public ?int $featuredSortOrder,
        public int $techTagsCount,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $techTags = [],
        public ?ThumbnailDto $coverThumbnail = null,
    ) {
    }

    /**
     * @param  object  $row  The locale-resolved list projection row.
     * @param  SkillOptionDto[]  $techTags
     * @param  StoredPicture|null  $cover  The derived first-screenshot picture.
     */
    public static function fromRow(object $row, array $techTags = [], ?StoredPicture $cover = null): self
    {
        return new self(
            id: (int) $row->id,
            slug: $row->slug,
            title: $row->title,
            briefDescription: $row->brief_description ?? '',
            publishedAt: $row->published_at === null || $row->published_at === ''
                ? null
                : new DateTimeImmutable($row->published_at),
            coverImage: $cover?->url,
            links: new ProjectLinksDto(
                sourceCodeUrl: $row->github_url ?? null,
                liveDemoUrl: $row->live_demo_url ?? null,
            ),
            isFeatured: (bool) ($row->is_featured ?? false),
            isPublished: (bool) ($row->is_published ?? false),
            featuredSortOrder: isset($row->featured_sort_order) && $row->featured_sort_order !== null
                ? (int) $row->featured_sort_order
                : null,
            techTagsCount: (int) ($row->tech_tags_count ?? 0),
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: new DateTimeImmutable($row->updated_at),
            techTags: $techTags,
            coverThumbnail: ThumbnailDto::fromStored($cover?->toThumbnail()),
        );
    }
}
