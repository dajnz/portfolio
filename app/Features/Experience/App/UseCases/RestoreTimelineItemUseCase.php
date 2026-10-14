<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Restores a soft-deleted timeline item — clears `deleted_at`, making it
 * visible again on the public timeline (when published) and the default
 * admin list.
 */
final class RestoreTimelineItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCacheInvalidator,
    ) {
    }

    public function restore(int $id): void
    {
        $this->repository->restore($id);
        $this->countCacheInvalidator->invalidate('experience');

    }
}
