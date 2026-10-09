<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The 1–5 proficiency scale of a tech skill, mirrored by the
 * `skills_proficiency_check` DB CHECK constraint. The range invariant
 * lives here, not in the consuming entities.
 */
final readonly class ProficiencyLevel
{
    public function __construct(
        private int $value,
    ) {
        if ($value < 1 || $value > 5) {
            throw new InvalidArgumentException(
                "Proficiency level must be between 1 and 5, got {$value}."
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
