<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

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
