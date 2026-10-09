<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * The featured drag-drop reorder: writes `featured_sort_order` as the
 * zero-indexed position of the submitted `{ "ids": [...] }` payload,
 * atomically.
 */
final class ReorderFeaturedProjectsUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    /**
     * @param  int[]  $ids  Featured project IDs in the new drag order.
     */
    public function reorder(array $ids): void
    {
        $this->repository->reorderFeatured($ids);

        $this->countCache->invalidate('projects');
    }
}
