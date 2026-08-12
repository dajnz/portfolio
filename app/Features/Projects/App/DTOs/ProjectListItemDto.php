<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use DateTimeImmutable;

/**
 * A project as a list item — the shared shape of the admin table
 * rows, the admin autocomplete items and the public index cards (each
 * controller maps the fields its output needs). Built by the use cases
 * from the raw list rows; the dates are converted at the boundary.
 * Immutable.
 */
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
        public ?ProjectImageDto $preview,
        public ProjectLinksDto $links,
        public bool $isFeatured,
        public bool $isPublished,
        public ?int $featuredSortOrder,
        public int $techTagsCount,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $techTags = [],
    ) {
    }

    /**
     * @param  object  $row  The locale-resolved list projection row.
     * @param  SkillOptionDto[]  $techTags
     */
    public static function fromRow(object $row, array $techTags = []): self
    {
        return new self(
            id: (int) $row->id,
            slug: $row->slug,
            title: $row->title,
            briefDescription: $row->brief_description ?? '',
            publishedAt: $row->published_at === null || $row->published_at === ''
                ? null
                : new DateTimeImmutable($row->published_at),
            preview: $row->preview_image === null || $row->preview_image === ''
                ? null
                : new ProjectImageDto(
                    url: $row->preview_image,
                    altLabels: self::decodeJsonMap($row->preview_image_alt ?? null) ?? [],
                ),
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
        );
    }

    /**
     * @return array<string, string>|null
     */
    private static function decodeJsonMap(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
