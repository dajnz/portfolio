<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Soft-deletes projects (single + bulk). Files stay in RustFS — the
 * admin may restore the entity.
 */
final class DestroyProjectUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function destroy(int $id): void
    {
        $this->repository->delete($id);

        $this->countCache->invalidate('projects');
    }

    /**
     * @param  int[]  $ids
     */
    public function destroyMany(array $ids): void
    {
        $this->repository->deleteMany($ids);

        $this->countCache->invalidate('projects');
    }
}
