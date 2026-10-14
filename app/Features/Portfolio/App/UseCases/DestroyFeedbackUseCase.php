<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

final class DestroyFeedbackUseCase
{
    public function __construct(
        private readonly FeedbackRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCacheInvalidator,
    ) {
    }

    public function destroy(int $id): void
    {
        $this->repository->delete($id);
        $this->countCacheInvalidator->invalidate('feedback');

    }
}
