<?php

declare(strict_types=1);

namespace App\Features\TechSkills\App\UseCases;

use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;

/**
 * Deletes a skill group. The `skill_groups` FKs cascade: the group's
 * translation rows and its skills are removed with it (and the skills'
 * pivot rows in turn).
 */
final class DestroySkillGroupUseCase
{
    public function __construct(
        private readonly TechSkillWriteRepositoryInterface $repository,
    ) {
    }

    public function destroy(int $id): void
    {
        $this->repository->deleteGroup($id);
    }
}
