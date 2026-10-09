<?php

declare(strict_types=1);

namespace App\Features\TechSkills\App\UseCases;

use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;

/**
 * Updates a skill group: the non-translatable columns plus the per-locale
 * names (upsert; rows for locales absent from the submission are
 * preserved).
 */
final class UpdateSkillGroupUseCase
{
    public function __construct(
        private readonly TechSkillWriteRepositoryInterface $repository,
        private readonly ActiveLocaleService $locales,
    ) {
    }

    /**
     * @param  array<string, string|null>  $names  Locale => localized name.
     */
    public function update(int $id, ?string $icon, int $sortOrder, array $names): void
    {
        $this->repository->saveGroup(new TechSkillGroup(
            id: $id,
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
