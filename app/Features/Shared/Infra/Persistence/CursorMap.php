<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Persistence;

/**
 * The `cursors` map of an admin list page response
 * (admin-section-ui.md:169-181): `first`/`last` are the cursor positions
 * of the page's boundary items (used for previous/next navigation;
 * `null` on an empty page) and `page` is the current page number.
 */
final readonly class CursorMap
{
    public function __construct(
        public ?Cursor $first,
        public ?Cursor $last,
        public int $page,
    ) {
    }

    /**
     * @return array{first: array{id: int, sort_value: mixed}|null, last: array{id: int, sort_value: mixed}|null, page: int}
     */
    public function toArray(): array
    {
        return [
            'first' => $this->first?->toArray(),
            'last' => $this->last?->toArray(),
            'page' => $this->page,
        ];
    }
}
