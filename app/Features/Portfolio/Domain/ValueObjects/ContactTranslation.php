<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a single localized contact name (`contact_translations`,
 * PK `(contact_id, locale)`). Immutable; carries no identity of its own.
 */
final readonly class ContactTranslation
{
    public function __construct(
        private string $locale,
        private string $name,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Contact translation name must not be empty.');
        }
    }

    public function toLocale(): string
    {
        return $this->locale;
    }

    public function toName(): string
    {
        return $this->name;
    }
}
