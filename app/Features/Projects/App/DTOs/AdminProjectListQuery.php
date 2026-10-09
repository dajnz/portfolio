<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The admin projects list query (admin-section-ui.md:327-346): the
 * filter bar (title search `?q=`, the featured toggle, the tech-tag
 * AND-intersect `?tags=`, the published status and the trashed view)
 * plus the cursor pagination params. The controller extracts the raw
 * query values; the paginator parsing happens in the infra
 * implementation (which rebuilds the HTTP request shape from `url` so
 * the ADR-0024 empty-page redirects keep targeting the real URL).
 * Immutable.
 */
final readonly class AdminProjectListQuery
{
    /**
     * @param  int[]  $tags
     * @param  array<string, mixed>|null  $cursorParams  Raw `?cursor[...]` values.
     */
    public function __construct(
        public string $search,
        public bool $featured,
        public array $tags,
        public ?bool $published,
        public bool $trashed,
        public int $page,
        public ?array $cursorParams,
        public ?string $sortColumn,
        public ?string $sortDirection,
        public string $url,
    ) {
    }
}
