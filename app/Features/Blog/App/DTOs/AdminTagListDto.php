<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

/**
 * The admin tag list page result: the tag rows plus the cursor
 * pagination facts. Immutable.
 */
final readonly class AdminTagListDto
{
    /**
     * @param  TagListItemDto[]  $rows
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
    ) {
    }
}
