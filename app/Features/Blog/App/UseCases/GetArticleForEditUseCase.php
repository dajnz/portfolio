<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ArticleDataDto;
use App\Features\Blog\App\DTOs\ArticleEditorDataDto;
use App\Features\Blog\App\DTOs\ArticleOptionDto;
use App\Features\Blog\App\DTOs\RelatedArticleDto;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface;
use App\Features\Shared\App\Contracts\PreviewUrlGeneratorInterface;
use Illuminate\Support\Facades\App;

/**
 * The article editor data (the create/edit form, blog.yaml#
 * BlogFormPageProps): the stored article data (null when creating —
 * hydrated with every aggregate part for the tabbed editor and mapped
 * to the data DTOs here), the tag picker options, the published
 * articles of the Related Articles multi-select and the signed 1-hour
 * preview URL (routes.md:155 — the preview button opens it in a new
 * tab; null on the create form). Trashed articles reach the form too
 * (restore happens from the form's own view, ADR-0006).
 */
final class GetArticleForEditUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly ArticleListQueryRepositoryInterface $listQuery,
        private readonly PreviewUrlGeneratorInterface $previewUrls,
    ) {
    }

    public function get(?int $id, string $locale): ArticleEditorDataDto
    {
        $article = $id === null
            ? null
            : $this->repository->findByIdIncludingTrashed($id);

        return new ArticleEditorDataDto(
            article: $article === null ? null : $this->articleData($article, $locale),
            tags: $this->tagOptions(),
            articles: array_map(
                static fn (object $articleRow): ArticleOptionDto => new ArticleOptionDto(
                    id: (int) $articleRow->id,
                    slug: $articleRow->slug,
                    title: $articleRow->title,
                ),
                $this->listQuery->findPublishedOptions($id, $locale),
            ),
            previewUrl: $id === null
                ? null
                : $this->previewUrls->temporarySigned('blog.preview', 3600, ['article' => $id]),
        );
    }

    /**
     * The full article data DTO: the aggregate parts plus the
     * locale-resolved tag names, skill tags and related rows (the
     * tag DTOs carry the pivot ids).
     */
    private function articleData(Article $article, string $locale): ArticleDataDto
    {
        $articleId = $article->toId();

        return ArticleDataDto::fromEntity(
            article: $article,
            readingTimeMinutes: $article->readingTimeFor($locale),
            tags: $this->tagOptionsOf($articleId),
            skillTags: $this->listQuery->skillTagsFor([$articleId])[$articleId] ?? [],
            related: array_map(
                static fn (object $related): RelatedArticleDto => new RelatedArticleDto(
                    id: (int) $related->id,
                    title: $related->title,
                ),
                $this->listQuery->relatedRowsFor([$articleId])[$articleId] ?? [],
            ),
        );
    }

    /**
     * The tag picker options of the form (all article tags).
     *
     * @return TagOptionDto[]
     */
    private function tagOptions(): array
    {
        return array_map(
            static fn (object $tag): TagOptionDto => new TagOptionDto(
                id: (int) $tag->id,
                slug: $tag->slug,
                name: $tag->name,
            ),
            $this->listQuery->findAllTags($this->locale()),
        );
    }

    /**
     * The article's own tag options.
     *
     * @return TagOptionDto[]
     */
    private function tagOptionsOf(int $articleId): array
    {
        return array_map(
            static fn (object $tag): TagOptionDto => new TagOptionDto(
                id: (int) $tag->id,
                slug: $tag->slug,
                name: $tag->name,
            ),
            $this->listQuery->articleTagsFor([$articleId])[$articleId] ?? [],
        );
    }

    private function locale(): string
    {
        return App::getLocale();
    }
}
