<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\App\DTOs\PaginationDto;

/**
 * The admin autocomplete search result (admin-section-ui.md:539-572):
 * the matched projects plus the length-aware pagination facts.
 * Immutable.
 */
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
