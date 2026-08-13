<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

/**
 * The admin article list page result: the item rows plus the cursor
 * pagination facts and the tag picker options of the list page
 * (blog.yaml#BlogListPageProps.tags). Immutable.
 */
final readonly class AdminArticleListDto
{
    /**
     * @param  ArticleListItemDto[]  $rows
     * @param  TagOptionDto[]  $tags  All article tags (the tag picker options).
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
        public array $tags,
    ) {
    }
}
