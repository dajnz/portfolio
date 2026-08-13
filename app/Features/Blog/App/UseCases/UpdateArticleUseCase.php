<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ArticleDraftDto;
use App\Features\Blog\App\Services\ArticleEntityFactory;
use App\Features\Blog\App\Services\ArticleSlugService;
use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;
use DateTimeImmutable;

/**
 * Updates an article: the slug is re-resolved per A3 — a submitted
 * slug equal to the stored one (or to the naive auto-generation of the
 * EN title) is regenerated server-side with a numeric collision
 * suffix, a differing submission is a manual override kept as-is; the
 * rest of the payload replaces the aggregate parts via the
 * repository's syncs. The publication state is derived from the
 * EXISTING aggregate — `published_at` is immutable after the first
 * publish (database.md:341): a first form-publish runs
 * {@see Article::publish()} (invariants asserted), an unpublish keeps
 * the timestamp. A slug change records the old slug in `old_slugs`
 * (B6). The obsolete lead image (replaced or removed) is deleted via
 * the shared asset cleaner after the save — the repository stays
 * persistence-only. A save touching a published article (or
 * unpublishing one) changes the sitemap — the cache is invalidated
 * here (the app layer owns the invalidation decisions).
 */
final class UpdateArticleUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly ArticleEntityFactory $factory,
        private readonly ArticleSlugService $slugs,
        private readonly AssetCleanerInterface $assets,
        private readonly CountCacheInvalidatorInterface $countCache,
        private readonly SitemapInvalidatorInterface $sitemap,
    ) {
    }

    public function update(int $id, ArticleDraftDto $draft): void
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw ArticleNotFoundException::forId($id);
        }

        $slug = $this->slugs->resolve(
            $draft->submittedSlug,
            $existing->toSlug(),
            $draft->translations->enTitle(),
            $id,
        );

        $article = $this->factory->make(
            id: new ArticleId($id),
            slug: $slug,
            published: $existing->toPublishedState(),
            draft: $draft,
        );

        if ($draft->isPublished && ! $existing->toIsPublished()) {
            $article->publish(new DateTimeImmutable('now'));
        } elseif (! $draft->isPublished) {
            $article->unpublish();
        }

        $this->repository->save($article);
        $this->assets->delete($this->obsoleteLeadImagePath($existing, $draft));

        if ($existing->toIsPublished() || $article->toIsPublished()) {
            $this->sitemap->invalidate();
        }

        $this->countCache->invalidate('articles');
    }

    /**
     * The RustFS file made obsolete by the lead-image replacement: the
     * old path when the submitted lead image differs or is absent
     * (content-management.md:813-819).
     *
     * @return string[]
     */
    private function obsoleteLeadImagePath(Article $existing, ArticleDraftDto $draft): array
    {
        $existingPath = $existing->toLeadImage()?->url;

        if ($existingPath === null) {
            return [];
        }

        return $draft->leadImage?->url === $existingPath ? [] : [$existingPath];
    }
}
