<?php

declare(strict_types=1);

namespace App\Features\TechSkills\App\UseCases;

use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;

/**
 * Loads all skill groups with their skills and translations for the
 * admin skills list page (`SkillGroupEditData` contract).
 */
final class ListSkillGroupsUseCase
{
    public function __construct(
        private readonly TechSkillWriteRepositoryInterface $repository,
    ) {
    }

    public function list(): array
    {
        return $this->repository->findAllGroups();
    }
}
