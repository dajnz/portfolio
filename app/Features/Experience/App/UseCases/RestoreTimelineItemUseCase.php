<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;

/**
 * Restores a soft-deleted timeline item (content-management.md:883) —
 * clears `deleted_at`, making it visible again on the public timeline
 * (when published) and the default admin list.
 */
final class RestoreTimelineItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
    ) {
    }

    public function restore(int $id): void
    {
        $this->repository->restore($id);
    }
}
