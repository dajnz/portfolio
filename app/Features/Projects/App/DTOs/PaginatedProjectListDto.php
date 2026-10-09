<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\App\DTOs\PaginationDto;

final readonly class PaginatedProjectListDto
{
    /**
     * @param  ProjectListItemDto[]  $rows
     * @param  array<int, array<int, SkillOptionDto>>  $techTags
     */
    public function __construct(
        public array $rows,
        public array $techTags,
        public PaginationDto $pagination,
    ) {
    }
}
