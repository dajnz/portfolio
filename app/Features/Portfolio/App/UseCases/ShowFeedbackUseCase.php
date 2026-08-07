<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\Entities\Feedback;
use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;

/**
 * Reads a single feedback submission for the admin detail page
 * (admin-section-ui.md:410-416).
 */
final class ShowFeedbackUseCase
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $repository,
    ) {
    }

    public function show(int $id): ?Feedback
    {
        return $this->repository->findById($id);
    }
}
