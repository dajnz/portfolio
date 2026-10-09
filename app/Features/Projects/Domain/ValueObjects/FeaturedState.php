<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\ValueObjects;

use App\Features\Shared\Domain\ValueObjects\SortOrder;

/**
 * Value object: the project's featured state — the `is_featured` flag
 * and the zero-indexed `featured_sort_order` ({@see SortOrder}, the
 * drag order of the landing "Featured Projects" section). The order is
 * nullable (an unfeatured project may keep its last order).
 */
final readonly class FeaturedState
{
    public function __construct(
        private bool $isFeatured,
        private ?SortOrder $featuredSortOrder = null,
    ) {
    }

    public function toIsFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function toFeaturedSortOrder(): ?int
    {
        return $this->featuredSortOrder?->toValue();
    }

    public function toSortOrder(): ?SortOrder
    {
        return $this->featuredSortOrder;
    }
}
