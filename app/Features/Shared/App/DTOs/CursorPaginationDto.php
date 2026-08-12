<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

/**
 * The cursor pagination facts of an admin list page (admin-section-ui.
 * md:169-181): the (cached) total, the page facts and the boundary
 * cursors. The controller maps these into the wire `meta` payload.
 * Immutable.
 */
final readonly class CursorPaginationDto
{
    public function __construct(
        public int $total,
        public int $perPage,
        public int $currentPage,
        public string $sortColumn,
        public string $sortDirection,
        public CursorMapDto $cursors,
    ) {
    }
}
