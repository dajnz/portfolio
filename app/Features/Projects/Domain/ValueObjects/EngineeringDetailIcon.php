<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: an engineering detail icon (`project_detail_items.icon`)
 * in the Iconify `set:name` form, from the curated `engineering` set
 * (config/portfolio.php → `icons.sets.engineering.icons` — e.g.
 * `heroicons:shield-check`, content-management.md:645). Immutable; the
 * format invariant lives here, not in the consuming entity.
 */
final readonly class EngineeringDetailIcon
{
    public function __construct(
        private string $value,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('Engineering detail icon value must not be empty.');
        }

        if (preg_match('/^[a-zA-Z0-9\-]+:[a-zA-Z0-9\-]+$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "Engineering detail icon must be an Iconify identifier (\"set:name\"), got \"{$value}\"."
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
