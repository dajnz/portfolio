<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;

/**
 * The publish/unpublish quick actions (single + bulk): `published_at`
 * is auto-set on the FIRST publish only and immutable thereafter — the
 * repository's `setPublicationMany()` keeps an already-set value
 * untouched. Every publication toggle changes the sitemap — the cache
 * is invalidated here (the app layer owns the invalidation decisions).
 */
final class UpdateArticlePublicationUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
        private readonly SitemapInvalidatorInterface $sitemap,
    ) {
    }

    public function set(int $id, bool $published): void
    {
        $this->repository->setPublicationMany([$id], $published);
        $this->sitemap->invalidate();

        $this->countCache->invalidate('articles');
    }

    /**
     * @param  int[]  $ids
     */
    public function setMany(array $ids, bool $published): void
    {
        $this->repository->setPublicationMany($ids, $published);
        $this->sitemap->invalidate();

        $this->countCache->invalidate('articles');
    }
}
