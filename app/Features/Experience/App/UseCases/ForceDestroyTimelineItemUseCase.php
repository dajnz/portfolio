<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;

/**
 * Permanently deletes a trashed timeline item: the row, its cascading
 * translations and the tech-tag pivot rows are removed.
 */
final class ForceDestroyTimelineItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
    ) {
    }

    public function forceDestroy(int $id): void
    {
        $this->repository->forceDelete($id);
        AdminCursorPaginator::invalidateCountCache('experience');

    }
}
