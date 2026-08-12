<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\Exceptions\ProjectSlugOccupiedException;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Restores soft-deleted projects (single + bulk). The slug uniqueness
 * is re-checked against NON-trashed projects — soft-deleted entities
 * occupy their slug, so a restored project must not collide with an
 * active one (B29, content-management.md:901). The checks are batched:
 * one read for the projects' slugs and one for the occupied subset.
 */
final class RestoreProjectUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function restore(int $id): void
    {
        $this->assertSlugAvailable($id);
        $this->repository->restore($id);

        $this->countCache->invalidate('projects');
    }

    /**
     * @param  int[]  $ids
     */
    public function restoreMany(array $ids): void
    {
        $slugsById = $this->repository->slugsOfProjects($ids);
        $occupied = $this->repository->slugsOccupiedByActive(array_values($slugsById));

        foreach ($slugsById as $slug) {
            if (in_array($slug, $occupied, true)) {
                throw ProjectSlugOccupiedException::forSlug($slug);
            }
        }

        $this->repository->restoreMany($ids);

        $this->countCache->invalidate('projects');
    }

    private function assertSlugAvailable(int $id): void
    {
        $slugs = $this->repository->slugsOfProjects([$id]);
        $slug = $slugs[$id] ?? null;

        if ($slug !== null
            && in_array($slug, $this->repository->slugsOccupiedByActive([$slug]), true)
        ) {
            throw ProjectSlugOccupiedException::forSlug($slug);
        }
    }
}
