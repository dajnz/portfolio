<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A single localized skill group name (`skill_group_translations`, PK
 * `(skill_group_id, locale)`). Carries no identity of its own.
 */
final readonly class TechSkillGroupName
{
    public function __construct(
        private string $locale,
        private string $name,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Skill group name must not be empty.');
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
