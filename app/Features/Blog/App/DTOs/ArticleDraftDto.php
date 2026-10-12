<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * The article data as submitted by the store/update forms — the whole
 * payload of the `StoreArticleUseCase`/`UpdateArticleUseCase`.
 * `submittedSlug` is the raw form value; the server re-resolves it
 * (the auto-generated case regenerates with a numeric collision
 * suffix, manual overrides are kept as-is). Composed of the granular
 * data DTOs. Immutable.
 */
final readonly class ArticleDraftDto
{
    /**
     * @param  int[]  $tagIds  Article-tag ids (exists:article_tags,id).
     * @param  int[]  $skillTagIds  Skill ids (exists:skills,id).
     * @param  int[]  $relatedArticleIds  Published, non-self article ids (exists + not_in:self).
     */
    public function __construct(
        public string $submittedSlug,
        public ArticleTranslationsDto $translations,
        public ?ArticleImageDto $leadImage,
        public bool $isPublished = false,
        public array $tagIds = [],
        public array $skillTagIds = [],
        public array $relatedArticleIds = [],
    ) {
    }
}
