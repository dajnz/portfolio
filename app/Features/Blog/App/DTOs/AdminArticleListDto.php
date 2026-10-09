<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

/**
 * The admin article list page result: the item rows plus the cursor
 * pagination facts, the tag picker options and the publication years
 * of the list page. Immutable.
 */
final readonly class AdminArticleListDto
{
    /**
     * @param  ArticleListItemDto[]  $rows
     * @param  TagOptionDto[]  $tags   All article tags (the tag picker options).
     * @param  int[]  $years  Distinct publication years, newest first (the year filter options).
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
        public array $tags,
        public array $years,
    ) {
    }
}
