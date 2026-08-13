<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\App\DTOs\PaginationDto;

/**
 * The admin autocomplete JSON variant result (routes.md:172-176,
 * admin-section-ui.md:539-572): item rows plus the length-aware
 * pagination facts. Immutable.
 */
final readonly class ArticleSearchResultDto
{
    /**
     * @param  ArticleListItemDto[]  $rows
     */
    public function __construct(
        public array $rows,
        public PaginationDto $pagination,
    ) {
    }
}
