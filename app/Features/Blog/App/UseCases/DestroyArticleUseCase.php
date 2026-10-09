<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;

/**
 * Soft-deletes articles (single + bulk). A deleted published article
 * leaves the sitemap — the cache is invalidated here (the app layer
 * owns the invalidation decisions; the published-state probe is the
 * repository's `publishedIdsOf()`).
 */
final class DestroyArticleUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
        private readonly SitemapInvalidatorInterface $sitemap,
    ) {
    }

    public function destroy(int $id): void
    {
        $published = $this->repository->publishedIdsOf([$id]);
        $this->repository->delete($id);

        if ($published !== []) {
            $this->sitemap->invalidate();
        }

        $this->countCache->invalidate('articles');
    }

    /**
     * @param  int[]  $ids
     */
    public function destroyMany(array $ids): void
    {
        $published = $this->repository->publishedIdsOf($ids);
        $this->repository->deleteMany($ids);

        if ($published !== []) {
            $this->sitemap->invalidate();
        }

        $this->countCache->invalidate('articles');
    }
}
