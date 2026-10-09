<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\App\DTOs\PaginationDto;

final readonly class ProjectSearchResultDto
{
    /**
     * @param  ProjectListItemDto[]  $rows
     */
    public function __construct(
        public array $rows,
        public PaginationDto $pagination,
    ) {
    }
}
