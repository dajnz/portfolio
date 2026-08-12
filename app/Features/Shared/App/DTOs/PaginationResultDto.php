<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

/**
 * The result of a cursor-paginated admin list query: the raw rows of
 * the current page (query-builder objects — the consuming use case
 * maps them to its own DTOs) plus the pagination facts. Immutable.
 */
final readonly class PaginationResultDto
{
    /**
     * @param  array<int, object>  $rows
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
    ) {
    }
}
