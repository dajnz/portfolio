<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

/**
 * A cursor position of a cursor-paginated list: the item id plus the
 * active sort column's value (`null` when sorting by the id column itself).
 */
final readonly class CursorDto
{
    public function __construct(
        public int $id,
        public mixed $sortValue,
    ) {
    }
}
