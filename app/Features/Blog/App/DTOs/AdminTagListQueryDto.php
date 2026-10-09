<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * The app-layer admin tag list query of `GET /admin/tags`: the search
 * value (name across all locales) plus the cursor-pagination request
 * facts. Immutable.
 */
final readonly class AdminTagListQueryDto
{
    /**
     * @param  array<string, mixed>|null  $cursorParams
     */
    public function __construct(
        public string $search,
        public int $page,
        public ?array $cursorParams,
        public ?string $sortColumn,
        public ?string $sortDirection,
        public string $url,
    ) {
    }
}
