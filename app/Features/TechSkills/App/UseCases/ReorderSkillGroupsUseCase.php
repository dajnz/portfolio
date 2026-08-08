<?php

declare(strict_types=1);

namespace App\Features\TechSkills\App\UseCases;

use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;

/**
 * Reorders skill groups to the zero-indexed positions of the submitted
 * `{ "ids": [...] }` payload (A4); the repository applies the writes
 * atomically.
 */
final class ReorderSkillGroupsUseCase
{
    public function __construct(
        private readonly TechSkillWriteRepositoryInterface $repository,
    ) {
    }

    /**
     * @param  int[]  $ids  Group IDs in the new drag order.
     */
    public function reorder(array $ids): void
    {
        $this->repository->reorderGroups($ids);
    }
}
