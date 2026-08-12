<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

/**
 * The `cursors` map of a cursor-paginated list (admin-section-ui.md:
 * 169-181): the boundary cursor positions of the current page
 * (previous/next navigation; null on an empty page) and the current
 * page number. Immutable.
 */
final readonly class CursorMapDto
{
    public function __construct(
        public ?CursorDto $first,
        public ?CursorDto $last,
        public int $page,
    ) {
    }
}
