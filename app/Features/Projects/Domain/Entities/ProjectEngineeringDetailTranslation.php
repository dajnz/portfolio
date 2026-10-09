<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\Entities;

use InvalidArgumentException;

final readonly class ProjectEngineeringDetailTranslation
{
    public function __construct(
        private string $locale,
        private string $title,
        private string $description,
    ) {
        if ($locale === '') {
            throw new InvalidArgumentException('Project engineering detail translation locale must not be empty.');
        }

        if ($title === '') {
            throw new InvalidArgumentException('Project engineering detail translation title must not be empty.');
        }

        if ($description === '') {
            throw new InvalidArgumentException('Project engineering detail translation description must not be empty.');
        }
    }

    public function toLocale(): string
    {
        return $this->locale;
    }

    public function toTitle(): string
    {
        return $this->title;
    }

    public function toDescription(): string
    {
        return $this->description;
    }
}
