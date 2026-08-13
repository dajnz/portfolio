<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * The app-layer admin list query of `GET /admin/blog`
 * (admin-section-ui.md:387-403): the filter bar values (search, the
 * separate article-tag / skill-tag AND-intersects per B2, the
 * published status, the publication year) plus the trashed view and
 * the cursor-pagination request facts (page / raw cursor params /
 * sort overrides and the full request URL — the paginator's empty-page
 * redirects keep the real URL, B175). Immutable.
 */
final readonly class AdminArticleListQueryDto
{
    /**
     * @param  int[]  $articleTagIds
     * @param  int[]  $skillTagIds
     * @param  array<string, mixed>|null  $cursorParams
     */
    public function __construct(
        public string $search,
        public array $articleTagIds,
        public array $skillTagIds,
        public ?bool $published,
        public ?int $year,
        public bool $trashed,
        public int $page,
        public ?array $cursorParams,
        public ?string $sortColumn,
        public ?string $sortDirection,
        public string $url,
    ) {
    }
}
