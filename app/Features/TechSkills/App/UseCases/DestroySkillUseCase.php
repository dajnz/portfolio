<?php

declare(strict_types=1);

namespace App\Features\TechSkills\App\UseCases;

use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;

/**
 * Deletes a skill. The skill stays referenced by project/experience/blog
 * pivot rows only as long as those pivots exist — the FKs cascade.
 */
final class DestroySkillUseCase
{
    public function __construct(
        private readonly TechSkillWriteRepositoryInterface $repository,
    ) {
    }

    public function destroy(int $id): void
    {
        $this->repository->deleteSkill($id);
    }
}
