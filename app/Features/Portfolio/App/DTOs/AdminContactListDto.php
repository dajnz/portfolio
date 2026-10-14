<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\DTOs;

use App\Features\Shared\App\DTOs\CursorPaginationDto;

/**
 * The admin contact list page result: the contact rows plus the cursor
 * pagination facts. Immutable.
 */
final readonly class AdminContactListDto
{
    /**
     * @param  ContactListItemDto[]  $rows
     */
    public function __construct(
        public array $rows,
        public CursorPaginationDto $pagination,
    ) {
    }
}
