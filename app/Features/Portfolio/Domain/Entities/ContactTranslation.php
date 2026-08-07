<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\Entities;

use InvalidArgumentException;

/**
 * A single localized contact name (`contact_translations`, PK
 * `(contact_id, locale)` — content-management.md:105-110).
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
