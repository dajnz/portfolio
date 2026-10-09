<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;

/**
 * Soft-deletes a timeline item — the row stays with `deleted_at` set,
 * hidden from the public timeline and the default admin list.
 */
final class DestroyTimelineItemUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
    ) {
    }

    public function destroy(int $id): void
    {
        $this->repository->delete($id);
        AdminCursorPaginator::invalidateCountCache('experience');

    }
}
