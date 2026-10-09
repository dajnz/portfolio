<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

final class DetachProjectTagUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function detach(int $projectId, int $skillId): void
    {
        $this->repository->detachTag($projectId, $skillId);

        $this->countCache->invalidate('projects');
    }
}
