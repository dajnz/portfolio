<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\DTOs;

/**
 * The app-layer admin contacts list query of `GET /admin/contacts`: the
 * cursor-pagination request facts (page / raw cursor params / sort
 * overrides and the full request URL — the paginator's empty-page
 * redirects keep the real URL). Immutable.
 */
final readonly class AdminContactListQueryDto
{
    /**
     * @param  array<string, mixed>|null  $cursorParams
     */
    public function __construct(
        public int $page,
        public ?array $cursorParams,
        public ?string $sortColumn,
        public ?string $sortDirection,
        public string $url,
    ) {
    }
}
