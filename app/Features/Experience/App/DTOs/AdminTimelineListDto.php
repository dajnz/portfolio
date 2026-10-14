<?php

declare(strict_types=1);

namespace App\Features\Experience\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

/**
 * The admin experience list page result: the item rows plus the cursor
 * pagination facts. Immutable.
 */
final readonly class AdminTimelineListDto
{
    /**
     * @param  TimelineItemRowDto[]  $rows
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
    ) {
    }
}
