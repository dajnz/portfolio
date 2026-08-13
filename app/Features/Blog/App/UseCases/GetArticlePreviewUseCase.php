<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ArticleDataDto;
use App\Features\Blog\App\DTOs\RelatedArticleDto;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface;

/**
 * The signed preview data of an article (routes.md:155 — the
 * `PreviewController` renders the PUBLIC article page): the full
 * article data with its resolved tags and related rows, bypassing the
 * `is_published` scope. Step 8.2 replaces this with the full public
 * use case (the shared Markdown pipeline + SEO + reactions); the
 * preview here serves the raw stored data.
 */
final class GetArticlePreviewUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly ArticleListQueryRepositoryInterface $listQuery,
    ) {
    }

    public function get(int $id, string $locale): ArticleDataDto
    {
        $article = $this->repository->findByIdIncludingTrashed($id);

        if ($article === null) {
            throw ArticleNotFoundException::forId($id);
        }

        $articleId = $article->toId();

        return ArticleDataDto::fromEntity(
            article: $article,
            readingTimeMinutes: $article->readingTimeFor($locale),
            tags: array_map(
                static fn (object $tag): TagOptionDto => new TagOptionDto(
                    id: (int) $tag->id,
                    slug: $tag->slug,
                    name: $tag->name,
                ),
                $this->listQuery->articleTagsFor([$articleId])[$articleId] ?? [],
            ),
            skillTags: $this->listQuery->skillTagsFor([$articleId])[$articleId] ?? [],
            related: array_map(
                static fn (object $related): RelatedArticleDto => new RelatedArticleDto(
                    id: (int) $related->id,
                    title: $related->title,
                ),
                $this->listQuery->relatedFor([$articleId])[$articleId] ?? [],
            ),
        );
    }
}
