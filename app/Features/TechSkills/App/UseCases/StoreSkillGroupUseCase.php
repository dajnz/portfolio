<?php

declare(strict_types=1);

namespace App\Features\TechSkills\App\UseCases;

use App\Features\Shared\App\Contracts\ActiveLocaleInterface;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;
use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;

/**
 * Creates a skill group with its per-locale names (only the locales
 * active in the `supported_locales` setting are persisted).
 */
final class StoreSkillGroupUseCase
{
    public function __construct(
        private readonly TechSkillWriteRepositoryInterface $repository,
        private readonly ActiveLocaleInterface $locales,
    ) {
    }

    /**
     * @param  array<string, string|null>  $names  Locale => localized name.
     */
    public function store(?string $icon, int $sortOrder, array $names): void
    {
        $this->repository->saveGroup(new TechSkillGroup(
            id: 0,
            icon: $icon === null ? null : new SkillGroupIcon($icon),
            sortOrder: new SortOrder($sortOrder),
            groupNames: $this->groupNames($names),
            skills: [],
        ));
    }

    /**
     * @param  array<string, string|null>  $names
     * @return TechSkillGroupName[]
     */
    private function groupNames(array $names): array
    {
        $groupNames = [];
        foreach ($this->locales->activeLocales() as $locale) {
            $name = $names[$locale] ?? null;

            if ($name === null || $name === '') {
                continue;
            }

            $groupNames[] = new TechSkillGroupName(locale: $locale, name: $name);
        }

        return $groupNames;
    }
}
