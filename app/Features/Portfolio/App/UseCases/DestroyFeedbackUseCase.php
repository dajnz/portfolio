<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;

/**
 * Deletes a feedback submission (admin-section-ui.md:412-416).
 */
final class DestroyFeedbackUseCase
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $repository,
    ) {
    }

    public function destroy(int $id): void
    {
        $this->repository->delete($id);
        AdminCursorPaginator::invalidateCountCache('feedback');

    }
}
