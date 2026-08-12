<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

/**
 * The admin projects list result — the list items plus the cursor
 * pagination facts. Immutable.
 */
final readonly class AdminProjectListDto
{
    /**
     * @param  ProjectListItemDto[]  $rows
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
    ) {
    }
}
