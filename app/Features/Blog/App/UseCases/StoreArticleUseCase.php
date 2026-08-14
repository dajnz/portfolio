<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ArticleDraftDto;
use App\Features\Blog\App\Services\ArticleDraftValidator;
use App\Features\Blog\App\Services\ArticleEntityFactory;
use App\Features\Blog\App\Services\ArticleSlugService;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Domain\ValueObjects\ArticleId;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use DateTimeImmutable;

/**
 * Creates an article (content-management.md:191-274): the resolved
 * slug (A3 — auto-generated from the EN title with a collision suffix,
 * or the manual override kept as-is — the override's uniqueness and
 * the related-link targets are checked by {@see ArticleDraftValidator}
 * first), the shared fields, the per-locale translations (title +
 * short description + content required per ACTIVE locale), the lead
 * image, the featured flag, the `article_tag_pivot` /
 * `article_skill_pivot` rows and the bidirectional `related_articles`
 * pairs. A form-save with `is_published` set publishes the fresh
 * aggregate ({@see \App\Features\Blog\Domain\Entities\Article::publish()}
 * asserts the publication invariants). The repository persists the
 * aggregate in one transaction. A published article enters the
 * sitemap — the cache is invalidated here (the app layer owns the
 * invalidation decisions).
 */
final class StoreArticleUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly ArticleEntityFactory $factory,
        private readonly ArticleSlugService $slugs,
        private readonly ArticleDraftValidator $validator,
        private readonly CountCacheInvalidatorInterface $countCache,
        private readonly SitemapInvalidatorInterface $sitemap,
    ) {
    }

    public function store(ArticleDraftDto $draft): void
    {
        $this->validator->assertSlugOverrideAvailable(
            $draft->submittedSlug,
            null,
            $draft->translations->enTitle(),
            null,
        );
        $this->validator->assertRelatedLinkable(0, $draft->relatedArticleIds);

        $slug = $this->slugs->resolve(
            $draft->submittedSlug,
            null,
            $draft->translations->enTitle(),
            null,
        );

        $article = $this->factory->make(
            id: new ArticleId(0),
            slug: $slug,
            published: PublishedState::unpublished(),
            draft: $draft,
        );

        if ($draft->isPublished) {
            $article->publish(new DateTimeImmutable('now'));
        }

        $this->repository->save($article);

        if ($article->toIsPublished()) {
            $this->sitemap->invalidate();
        }

        $this->countCache->invalidate('articles');
    }
}
