<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Permanently deletes projects (single + bulk) — the DB rows are
 * removed by the repository (with the `old_slugs` history records);
 * the RustFS files (preview image + screenshots) are deleted here via
 * the shared asset cleaner, orchestrated by this use case
 * (content-management.md:903-905).
 */
final class ForceDestroyProjectUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly AssetCleanerInterface $assets,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function forceDestroy(int $id): void
    {
        $paths = $this->assetPathsOf([$id]);
        $this->repository->forceDelete($id);
        $this->assets->delete($paths);

        $this->countCache->invalidate('projects');
    }

    /**
     * @param  int[]  $ids
     */
    public function forceDestroyMany(array $ids): void
    {
        $paths = $this->assetPathsOf($ids);
        $this->repository->forceDeleteMany($ids);
        $this->assets->delete($paths);

        $this->countCache->invalidate('projects');
    }

    /**
     * The projects' RustFS paths — read BEFORE the rows are deleted
     * (the cascade removes the screenshot rows too).
     *
     * @param  int[]  $ids
     * @return string[]
     */
    private function assetPathsOf(array $ids): array
    {
        $paths = [];
        foreach ($this->repository->assetPathsOf($ids) as $projectPaths) {
            $paths = array_merge($paths, $projectPaths);
        }

        return $paths;
    }
}
