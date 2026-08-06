<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Persistence;

use DateTimeInterface;

/**
 * A cursor position in the admin list result set: the item id plus the
 * active sort column's value (`null` when sorting by the id column
 * itself). Immutable and comparable; the seek-comparison logic lives in
 * the query layer (SortSpec).
 *
 * Carried in the admin list request as `?cursor[id]=&cursor[sort_value]=`
 * and exposed in the page response under `cursors.first` / `cursors.last`
 * (admin-section-ui.md:169-181).
 */
final readonly class Cursor
{
    public function __construct(
        public int $id,
        public mixed $sortValue,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id
            && $this->sortValue === $other->sortValue;
    }

    /**
     * @return array{id: int, sort_value: mixed}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sort_value' => $this->sortValue instanceof DateTimeInterface
                ? $this->sortValue->format(DateTimeInterface::ATOM)
                : $this->sortValue,
        ];
    }
}
