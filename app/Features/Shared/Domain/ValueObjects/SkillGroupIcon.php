<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a skill group icon (`skill_groups.icon`) in the
 * `heroicons:*` format (ADR-0002 — e.g. `heroicons:server`). Immutable; the
 * format invariant lives here, not in the consuming entities.
 */
final readonly class SkillGroupIcon
{
    public function __construct(
        private string $value,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('Skill group icon value must not be empty.');
        }

        if (preg_match('/^heroicons:[a-zA-Z][a-zA-Z0-9\-]*$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "Skill group icon must match the format \"heroicons:*\", got \"{$value}\"."
            );
        }
    }

    public function toValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }
}
