<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Permanently deletes a trashed timeline item: the row, its cascading
 * translations and the tech-tag pivot rows are removed.
 */
final class ForceDestroyTimelineItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCacheInvalidator,
    ) {
    }

    public function forceDestroy(int $id): void
    {
        $this->repository->forceDelete($id);
        $this->countCacheInvalidator->invalidate('experience');

    }
}
