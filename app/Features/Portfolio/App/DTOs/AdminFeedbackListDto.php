<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

/**
 * The admin feedback list page result: the feedback rows plus the cursor
 * pagination facts. Immutable.
 */
final readonly class AdminFeedbackListDto
{
    /**
     * @param  FeedbackListItemDto[]  $rows
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
    ) {
    }
}
