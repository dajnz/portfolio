<?php

declare(strict_types=1);

namespace App\Features\TechSkills\App\UseCases;

use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;

/**
 * Reorders skills per the `{ "skills": [{ "id", "skill_group_id" }] }`
 * payload: the zero-indexed array position becomes `sort_order` and the
 * submitted group assignment is persisted — this is what makes
 * cross-group drag-and-drop moves stick.
 */
final class ReorderSkillsUseCase
{
    public function __construct(
        private readonly TechSkillWriteRepositoryInterface $repository,
    ) {
    }

    /**
     * @param  array<int, array{id: int, skill_group_id: int}>  $skills  Skills in the new drag order.
     */
    public function reorder(array $skills): void
    {
        $this->repository->reorderSkills($skills);
    }
}
