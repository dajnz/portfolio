<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Blog\Domain\Entities\Article;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use DateTimeImmutable;

/**
 * The full stored article data — every aggregate part as data DTOs:
 * the identity, the per-locale translations (the editor's LocaleTabs
 * maps), the lead image, the state flags and the resolved tag sets —
 * the tag DTOs CARRY the pivot ids (the `{id, slug, name}` options,
 * the `{id, name, icon}` skills, the `{id, title}` related rows), so
 * no separate id arrays are needed.
 * Returned by the read use cases; the controllers prepare their own
 * output shapes from it. Immutable.
 */
final readonly class ArticleDataDto
{
    /**
     * @param  TagOptionDto[]  $tags  The `article_tag_pivot` tags (locale-resolved names).
     * @param  TechSkillTag[]  $skillTags  The `article_skill_pivot` skills.
     * @param  RelatedArticleDto[]  $related  The related-article rows.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public ArticleTranslationsDto $translations,
        public ?ArticleImageDto $leadImage,
        public bool $isPublished,
        public ?DateTimeImmutable $publishedAt,
        public int $readingTimeMinutes,
        public array $tags = [],
        public array $skillTags = [],
        public array $related = [],
    ) {
    }

    /**
     * @param  TagOptionDto[]  $tags
     * @param  TechSkillTag[]  $skillTags
     * @param  RelatedArticleDto[]  $related
     */
    public static function fromEntity(
        Article $article,
        int $readingTimeMinutes,
        array $tags = [],
        array $skillTags = [],
        array $related = [],
    ): self {
        return new self(
            id: $article->toId(),
            slug: $article->toSlug(),
            translations: new ArticleTranslationsDto(
                titles: $article->titlesByLocale(),
                shortDescriptions: $article->shortDescriptionsByLocale(),
                contents: $article->contentsByLocale(),
            ),
            leadImage: $article->toLeadImage() === null
                ? null
                : new ArticleImageDto(
                    url: $article->toLeadImage()?->url,
                    altLabels: $article->toLeadImage()?->altLabelsMap() ?? [],
                ),
            isPublished: $article->toIsPublished(),
            publishedAt: $article->toPublishedAt(),
            readingTimeMinutes: $readingTimeMinutes,
            tags: $tags,
            skillTags: $skillTags,
            related: $related,
        );
    }
}
