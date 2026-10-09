<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Attaches a tech tag — a duplicate attachment surfaces as a 409 via
 * the domain exception.
 */
final class AttachProjectTagUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function attach(int $projectId, int $skillId): void
    {
        $this->repository->attachTag($projectId, $skillId);

        $this->countCache->invalidate('projects');
    }
}
