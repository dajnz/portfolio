<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * The inline featured star toggle (admin-section-ui.md:342): featuring
 * defaults `featured_sort_order` to the next available value (max + 1)
 * when not already set; unfeaturing preserves the value
 * (content-management.md:157).
 */
final class UpdateProjectFeaturedUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function set(int $id, bool $featured): void
    {
        $this->repository->setFeatured($id, $featured);

        $this->countCache->invalidate('projects');
    }
}
