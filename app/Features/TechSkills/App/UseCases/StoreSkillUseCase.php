<?php

declare(strict_types=1);

namespace App\Features\TechSkills\App\UseCases;

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\ValueObjects\ProficiencyLevel;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\TechSkills\Domain\Exceptions\DuplicateSkillNameException;
use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;
use InvalidArgumentException;

/**
 * Creates a skill in a group. Skill names are not translatable and must
 * be unique among other skills; the case-insensitive probe is
 * {@see TechSkillWriteRepositoryInterface::skillNameExists()}. The
 * group's context is loaded via the repository.
 */
final class StoreSkillUseCase
{
    public function __construct(
        private readonly TechSkillWriteRepositoryInterface $repository,
    ) {
    }

    /**
     * @throws DuplicateSkillNameException when another skill already uses the name
     */
    public function store(
        string $name,
        ?string $icon,
        int $proficiency,
        int $groupId,
        bool $includeInResume,
        int $sortOrder,
    ): void {
        if ($this->repository->skillNameExists($name)) {
            throw new DuplicateSkillNameException();
        }

        $this->repository->saveSkill(new TechSkill(
            id: 0,
            name: $name,
            icon: $icon === null ? null : new SkillIcon($icon),
            proficiency: new ProficiencyLevel($proficiency),
            includeInResume: $includeInResume,
            sortOrder: new SortOrder($sortOrder),
            group: $this->requireGroup($groupId)->toGroupVo(),
        ));
    }

    /**
     * The group is guaranteed to exist by the request's
     * `exists:skill_groups,id` rule; this is a defensive guard.
     */
    private function requireGroup(int $groupId): TechSkillGroup
    {
        $group = $this->repository->findGroupById($groupId);

        if ($group === null) {
            throw new InvalidArgumentException("Skill group {$groupId} not found.");
        }

        return $group;
    }
}
