<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a zero-based list position (`sort_order` column).
 * Immutable; the non-negative invariant lives here, not in the
 * consuming entities.
 */
final readonly class SortOrder
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException(
                "Sort order must be a positive 0-based integer, got {$value}."
            );
        }
    }

    public function toValue(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }
}
