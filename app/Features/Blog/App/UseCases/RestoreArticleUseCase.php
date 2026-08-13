<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\Exceptions\ArticleSlugOccupiedException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;

/**
 * Restores soft-deleted articles (single + bulk). The slug uniqueness
 * is re-checked against NON-trashed articles — soft-deleted entities
 * occupy their slug, so a restored article must not collide with an
 * active one (B29, content-management.md:901). The checks are batched:
 * one read for the articles' slugs and one for the occupied subset.
 * A restored published article re-enters the sitemap — the cache is
 * invalidated here (the app layer owns the invalidation decisions).
 */
final class RestoreArticleUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
        private readonly SitemapInvalidatorInterface $sitemap,
    ) {
    }

    public function restore(int $id): void
    {
        $this->assertSlugAvailable($id);
        $published = $this->repository->publishedIdsOf([$id]);
        $this->repository->restore($id);

        if ($published !== []) {
            $this->sitemap->invalidate();
        }

        $this->countCache->invalidate('articles');
    }

    /**
     * @param  int[]  $ids
     */
    public function restoreMany(array $ids): void
    {
        $slugsById = $this->repository->slugsOfArticles($ids);
        $occupied = $this->repository->slugsOccupiedByActive(array_values($slugsById));

        foreach ($slugsById as $slug) {
            if (in_array($slug, $occupied, true)) {
                throw ArticleSlugOccupiedException::forSlug($slug);
            }
        }

        $published = $this->repository->publishedIdsOf($ids);
        $this->repository->restoreMany($ids);

        if ($published !== []) {
            $this->sitemap->invalidate();
        }

        $this->countCache->invalidate('articles');
    }

    private function assertSlugAvailable(int $id): void
    {
        $slugs = $this->repository->slugsOfArticles([$id]);
        $slug = $slugs[$id] ?? null;

        if ($slug !== null
            && in_array($slug, $this->repository->slugsOccupiedByActive([$slug]), true)
        ) {
            throw ArticleSlugOccupiedException::forSlug($slug);
        }
    }
}
