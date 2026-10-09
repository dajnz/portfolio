<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\DTOs\ArticleCardDto;
use App\Features\Blog\App\DTOs\BlogSeoDto;
use App\Features\Blog\App\DTOs\PublicArticleDto;
use App\Features\Blog\App\DTOs\TagOptionDto;
use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\Exceptions\ArticleNotFoundException;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleReactionRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\PublicArticleReadRepositoryInterface;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use App\Features\Shared\App\Contracts\MarkdownContentPipelineInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;

/**
 * The public blog article detail (public-section-ui.md:240-297,
 * blog.yaml#BlogArticlePageProps): the published aggregate resolved
 * by slug, with the body rendered through the shared content pipeline
 * (the Redis-cached `article:html:{id}:{locale}:{updated_at}` HTML),
 * the resolved article tags, the published related cards, the
 * pre-increment view counter (content-management.md:217) and the
 * reaction state — `null` when the `social_reactions` feature flag
 * is disabled or no fingerprint was supplied (the signed preview —
 * zeroed widget, ADR-0004).
 *
 * Old-slug 301 resolution (ADR-0006) lives here too (`currentSlugFor()`).
 */
final class GetPublicArticleUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly PublicArticleReadRepositoryInterface $listQuery,
        private readonly ArticleReactionRepositoryInterface $reactions,
        private readonly FeatureFlagRepositoryInterface $flags,
        private readonly AboutInfoReadRepositoryInterface $about,
        private readonly MarkdownContentPipelineInterface $pipeline,
    ) {
    }

    public function get(string $slug, bool $includeUnpublished, string $locale, ?string $fingerprint): ?PublicArticleDto
    {
        $article = $includeUnpublished
            ? $this->repository->findBySlugIncludingUnpublished($slug)
            : $this->repository->findBySlug($slug);

        return $this->toDetailDto($article, $locale, $fingerprint);
    }

    /**
     * The signed preview variant (routes.md:155): resolves by raw id
     * (the route parameter is an integer), bypassing the
     * `is_published` scope.
     *
     * @throws ArticleNotFoundException
     */
    public function getById(int $id, string $locale): PublicArticleDto
    {
        $article = $this->repository->findByIdIncludingTrashed($id);

        if ($article === null) {
            throw ArticleNotFoundException::forId($id);
        }

        return $this->toDetailDto($article, $locale, null) ?? throw ArticleNotFoundException::forId($id);
    }

    /**
     * The current slug mapped from an old-slug record — the 301 target
     * of the public detail controller (ADR-0006, content-management.md:
     * 454-464). Null when the slug is not recorded (a trashed
     * article's history resolves to nothing).
     */
    public function currentSlugFor(string $slug): ?string
    {
        return $this->repository->currentSlugForOld($slug);
    }

    private function toDetailDto(?Article $article, string $locale, ?string $fingerprint): ?PublicArticleDto
    {
        if ($article === null) {
            return null;
        }

        $articleId = $article->toId();
        $siteName = $this->about->findSiteName($locale);
        $tagRows = $this->listQuery->articleTagsFor([$articleId])[$articleId] ?? [];
        $skillTags = $this->listQuery->skillTagsFor([$articleId])[$articleId] ?? [];

        return new PublicArticleDto(
            id: $articleId,
            slug: $article->toSlug(),
            title: $article->titleFor($locale),
            shortDescription: $article->shortDescriptionFor($locale),
            contentHtml: $this->pipeline->articleHtml(
                $articleId,
                $locale,
                $article->contentFor($locale),
            ),
            publishedAt: $article->toPublishedAt(),
            readingTimeMinutes: $article->readingTimeFor($locale),
            leadImage: $article->toLeadImage(),
            tags: array_map(
                static fn (object $tag): TagOptionDto => new TagOptionDto(
                    id: (int) $tag->id,
                    slug: $tag->slug,
                    name: $tag->name,
                ),
                $tagRows,
            ),
            skillTags: $skillTags,
            related: $this->relatedCards($articleId, $locale),
            views: $this->listQuery->viewsOf($articleId),
            reactions: $this->reactionsFor($articleId, $fingerprint),
            seo: BlogSeoDto::forDetail(
                title: $article->titleFor($locale),
                description: $article->shortDescriptionFor($locale),
                slug: $article->toSlug(),
                leadImagePath: $article->toLeadImage()?->url,
                siteName: $siteName,
                tagNames: array_map(static fn (object $tag): string => $tag->name, $tagRows),
                skillNames: array_map(
                    static fn ($skill): string => $skill->toSkillName(),
                    $skillTags,
                ),
                publishedAt: $article->toPublishedAt(),
                updatedAt: $article->toUpdatedAt(),
                sitePhotoPath: $this->about->findSitePhotoPath(),
            ),
            hasProjects: $this->listQuery->hasPublishedProjects(),
        );
    }

    /**
     * The reaction state: `null` when the flag is off or the
     * fingerprint is missing (the widget is not rendered —
     * public-section-components.md:1273); otherwise the visitor's
     * current reaction plus the totals.
     *
     * @return array{user_reaction: string|null, likes: int, dislikes: int}|null
     */
    private function reactionsFor(int $articleId, ?string $fingerprint): ?array
    {
        if ($fingerprint === null || ! $this->flags->isEnabled(FeatureFlagName::SocialReactions)) {
            return null;
        }

        $existing = $this->reactions->findByArticleAndFingerprint($articleId, $fingerprint);
        $counts = $this->reactions->countsFor($articleId);

        return [
            'user_reaction' => $existing?->toReaction()->value,
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
        ];
    }

    /**
     * @return ArticleCardDto[]
     */
    private function relatedCards(int $articleId, string $locale): array
    {
        $rows = $this->listQuery->findRelatedCards($articleId, $locale);

        if ($rows === []) {
            return [];
        }

        $relatedIds = array_map(static fn (object $row): int => (int) $row->id, $rows);
        $tagsByArticle = $this->listQuery->articleTagsFor($relatedIds);
        $skillsByArticle = $this->listQuery->skillTagsFor($relatedIds);

        return array_map(function (object $row) use ($tagsByArticle, $skillsByArticle): ArticleCardDto {
            $card = ArticleCardDto::fromRow($row);

            return new ArticleCardDto(
                id: $card->id,
                slug: $card->slug,
                title: $card->title,
                shortDescription: $card->shortDescription,
                publishedAt: $card->publishedAt,
                readingTimeMinutes: $card->readingTimeMinutes,
                leadImage: $card->leadImage,
                snippet: null,
                tags: array_map(
                    static fn (object $tag): TagOptionDto => new TagOptionDto(
                        id: (int) $tag->id,
                        slug: $tag->slug,
                        name: $tag->name,
                    ),
                    $tagsByArticle[$card->id] ?? [],
                ),
                skillTags: $skillsByArticle[$card->id] ?? [],
                leadImageAlt: $card->leadImageAlt,
            );
        }, $rows);
    }
}
