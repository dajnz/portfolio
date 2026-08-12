<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

/**
 * The admin projects list result — the list items plus the cursor
 * pagination facts and the featured projects of the live view (the
 * separate reorder section of the list page, admin-section-ui.md:338).
 * Immutable.
 */
final readonly class AdminProjectListDto
{
    /**
     * @param  ProjectListItemDto[]  $rows
     * @param  ProjectListItemDto[]  $featuredProjects
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
        public array $featuredProjects = [],
    ) {
    }
}
