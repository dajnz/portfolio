<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\Entities;

use InvalidArgumentException;

/**
 * A single localized about block (`about_info_translations`, PK
 * `(about_info_id, locale)` — database.md:89-99).
 */
final readonly class AboutInfoTranslation
{
    public function __construct(
        private string $locale,
        private string $name,
        private string $title,
        private string $shortPitch,
        private ?string $location,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('About translation name must not be empty.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('About translation title must not be empty.');
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

    public function toTitle(): string
    {
        return $this->title;
    }

    public function toShortPitch(): string
    {
        return $this->shortPitch;
    }

    public function toLocation(): ?string
    {
        return $this->location;
    }
}
