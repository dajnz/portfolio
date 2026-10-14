<?php

declare(strict_types=1);

namespace App\Features\Experience\App\DTOs;

/**
 * The app-layer admin experience list query of `GET /admin/experience`:
 * the trashed view and the content search (`?q=`) plus the
 * cursor-pagination request facts (page / raw cursor params / sort
 * overrides and the full request URL — the paginator's empty-page
 * redirects keep the real URL). Immutable.
 */
final readonly class AdminTimelineListQueryDto
{
    /**
     * @param  array<string, mixed>|null  $cursorParams
     */
    public function __construct(
        public bool $trashed,
        public string $search,
        public int $page,
        public ?array $cursorParams,
        public ?string $sortColumn,
        public ?string $sortDirection,
        public string $url,
    ) {
    }
}
