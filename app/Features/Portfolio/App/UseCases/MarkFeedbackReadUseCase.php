<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;

/**
 * Marks a feedback submission as read (fires automatically on the
 * detail-page mount via `useAutoMarkRead`).
 */
final class MarkFeedbackReadUseCase
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $repository,
    ) {
    }

    public function markRead(int $id): void
    {
        $this->repository->markRead($id);
    }
}
