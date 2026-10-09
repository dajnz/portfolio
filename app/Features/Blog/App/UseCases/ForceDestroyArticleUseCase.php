<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;

/**
 * Permanently deletes articles (single + bulk) — the DB rows are
 * removed by the repository (with the `old_slugs` history records);
 * the RustFS files (the lead image) are deleted here via the shared
 * asset cleaner, orchestrated by this use case. A force-deleted
 * published article leaves the sitemap — the cache is invalidated here
 * (the app layer owns the invalidation decisions).
 */
final class ForceDestroyArticleUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly AssetCleanerInterface $assets,
        private readonly CountCacheInvalidatorInterface $countCache,
        private readonly SitemapInvalidatorInterface $sitemap,
    ) {
    }

    public function forceDestroy(int $id): void
    {
        $paths = $this->assetPathsOf([$id]);
        $published = $this->repository->publishedIdsOf([$id]);
        $this->repository->forceDelete($id);
        $this->assets->delete($paths);

        if ($published !== []) {
            $this->sitemap->invalidate();
        }

        $this->countCache->invalidate('articles');
    }

    /**
     * @param  int[]  $ids
     */
    public function forceDestroyMany(array $ids): void
    {
        $paths = $this->assetPathsOf($ids);
        $published = $this->repository->publishedIdsOf($ids);
        $this->repository->forceDeleteMany($ids);
        $this->assets->delete($paths);

        if ($published !== []) {
            $this->sitemap->invalidate();
        }

        $this->countCache->invalidate('articles');
    }

    /**
     * The articles' RustFS paths — read BEFORE the rows are deleted.
     *
     * @param  int[]  $ids
     * @return string[]
     */
    private function assetPathsOf(array $ids): array
    {
        $paths = [];
        foreach ($this->repository->assetPathsOf($ids) as $articlePaths) {
            $paths = array_merge($paths, $articlePaths);
        }

        return $paths;
    }
}
