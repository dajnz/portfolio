<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;

/**
 * Marks a feedback submission as forwarded (best-effort: the admin UI
 * opens the `mailto:` link even when this call fails).
 */
final class MarkFeedbackForwardedUseCase
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $repository,
    ) {
    }

    public function markForwarded(int $id): void
    {
        $this->repository->markForwarded($id);
    }
}
