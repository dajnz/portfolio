<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * The publish/unpublish quick actions (single + bulk): flips
 * `is_published` — `published_at` is auto-set on the FIRST publish only
 * and immutable thereafter.
 */
final class UpdateProjectPublicationUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function set(int $id, bool $published): void
    {
        $this->repository->setPublicationMany([$id], $published);

        $this->countCache->invalidate('projects');
    }

    /**
     * @param  int[]  $ids
     */
    public function setMany(array $ids, bool $published): void
    {
        $this->repository->setPublicationMany($ids, $published);

        $this->countCache->invalidate('projects');
    }
}
