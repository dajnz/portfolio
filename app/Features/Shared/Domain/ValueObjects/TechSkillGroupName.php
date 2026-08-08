<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Value object: a single localized skill group name
 * (`skill_group_translations`, PK `(skill_group_id, locale)` —
 * database.md:144-151). Immutable; carries no identity of its own.
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
