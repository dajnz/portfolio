<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\App\DTOs\PaginationDto;

/**
 * The admin autocomplete JSON variant result: item rows plus the
 * length-aware pagination facts. Immutable.
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
