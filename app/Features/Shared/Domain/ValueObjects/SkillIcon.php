<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a tech skill icon (`skills.icon`) in the `devicon:*`
 * format (ADR-0002 — e.g. `devicon:laravel`). Immutable; the format invariant
 * lives here, not in the consuming entities.
 */
final readonly class SkillIcon
{
    public function __construct(
        private string $value,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('Skill icon value must not be empty.');
        }

        if (preg_match('/^devicon:[a-zA-Z][a-zA-Z0-9\-]*$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "Skill icon must match the format \"devicon:*\", got \"{$value}\"."
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
