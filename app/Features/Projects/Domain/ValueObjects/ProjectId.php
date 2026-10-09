<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a project's numeric identity (`projects.id`). The
 * value 0 marks a transient (unsaved) aggregate; the positive-integer
 * rule lives here, not in the consuming entity.
 */
final readonly class ProjectId
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 0) {
            throw new InvalidArgumentException('Project id must be a non-negative integer.');
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
