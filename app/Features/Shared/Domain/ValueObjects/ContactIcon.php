<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a contact icon (`contacts.icon`) in the generic Iconify
 * `set:name` form (e.g. `simple-icons:github`, `simple-icons:telegram`).
 * Immutable; the format invariant lives here, not in the consuming
 * entities.
 */
final readonly class ContactIcon
{
    public function __construct(
        private string $value,
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('Contact icon value must not be empty.');
        }

        if (preg_match('/^[a-z0-9-]+:[a-zA-Z0-9-]+$/', $value) !== 1) {
            throw new InvalidArgumentException(
                "Contact icon must be an Iconify identifier (\"set:name\"), got \"{$value}\"."
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
