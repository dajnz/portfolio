<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * In-memory repository fake for unit tests.
 */
final class InMemoryTechSkillReadRepository implements TechSkillReadRepositoryInterface
{
    public ?string $lastLocale = null;

    /**
     * @param  TechSkill[]     $skills
     * @param  TechSkillGroup[] $groups
     */
    public function __construct(
        private readonly array $skills,
        private readonly array $groups = [],
    ) {
    }

    /**
     * @return TechSkill[]
     */
    public function findAll(string $locale): array
    {
        $this->lastLocale = $locale;

        return $this->skills;
    }

    /**
     * @param  int[]  $ids
     * @return TechSkill[]
     */
    public function findByIds(array $ids, string $locale): array
    {
        return array_values(array_filter(
            $this->skills,
            static fn (TechSkill $skill): bool => in_array($skill->toId(), $ids, true),
        ));
    }

    /**
     * @return TechSkillGroup[]
     */
    public function findAllGrouped(string $locale): array
    {
        $this->lastLocale = $locale;

        return $this->groups;
    }
}
