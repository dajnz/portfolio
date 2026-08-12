<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\App\DTOs\PaginationDto;

/**
 * The length-aware project list result (the public JSON dual-response
 * variant — routes.md:199-212): the locale-resolved items, their tech
 * tags and the pagination facts. Immutable.
 */
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
