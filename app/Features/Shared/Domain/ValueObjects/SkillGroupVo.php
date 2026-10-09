<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

/**
 * The identity + per-locale names of a skill group, carried by
 * {@see \App\Features\Shared\Domain\Entities\TechSkill} so a skill is
 * never isolated from its group context. Built by the repositories
 * (hydration) and by
 * {@see \App\Features\Shared\Domain\Entities\TechSkillGroup::toGroupVo()}.
 */
final readonly class SkillGroupVo
{
    /**
     * @param  TechSkillGroupName[]  $groupNames
     */
    public function __construct(
        private int $id,
        private array $groupNames,
    ) {
    }

    public function toId(): int
    {
        return $this->id;
    }

    /**
     * @return TechSkillGroupName[]
     */
    public function toGroupNames(): array
    {
        return $this->groupNames;
    }

    /**
     * Localized group name for the given locale, falling back to `en` when
     * the translation is missing.
     */
    public function groupNameFor(string $locale): string
    {
        $names = [];
        foreach ($this->groupNames as $groupName) {
            $names[$groupName->toLocale()] = $groupName->toName();
        }

        return $names[$locale] ?? $names['en'] ?? '';
    }
}
