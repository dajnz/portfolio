<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Persistence;

/**
 * The result of an admin list pagination request — the spec-shaped
 * response documented in admin-section-ui.md:169-181: the raw items of
 * the current page, the (cached) total used by the page-number widget,
 * the current page number, the active sort, and the boundary cursors
 * for previous/next navigation. `items` holds raw models/rows; the
 * consuming controllers map them into their page props.
 */
final readonly class CursorPage
{
    /**
     * @param  array<int, mixed>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $perPage,
        public int $currentPage,
        public SortSpec $sorting,
        public CursorMap $cursors,
    ) {
    }

    /**
     * Empty page for a request that lies beyond the last page: no
     * items, no boundary cursors (admin-section-ui.md:151-161).
     */
    public static function empty(int $total, int $perPage, int $currentPage, SortSpec $sorting): self
    {
        return new self(
            items: [],
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            sorting: $sorting,
            cursors: new CursorMap(first: null, last: null, page: $currentPage),
        );
    }

    /**
     * @return array{
     *     items: array<int, mixed>,
     *     total: int,
     *     per_page: int,
     *     current_page: int,
     *     sort_column: string,
     *     sort_direction: string,
     *     cursors: array{first: array{id: int, sort_value: mixed}|null, last: array{id: int, sort_value: mixed}|null, page: int},
     * }
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'sort_column' => $this->sorting->sortColumn,
            'sort_direction' => $this->sorting->sortDirection,
            'cursors' => $this->cursors->toArray(),
        ];
    }
}
