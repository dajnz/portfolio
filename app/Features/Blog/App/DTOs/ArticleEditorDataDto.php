<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * The editor data of the create/edit form (blog.yaml#BlogFormPageProps):
 * the stored article data (null when creating), the tag picker
 * options, the published-articles list of the Related Articles
 * multi-select and the signed 1-hour preview URL (routes.md:155 — the
 * preview button opens it in a new tab; null on the create form).
 * Immutable.
 */
final readonly class ArticleEditorDataDto
{
    /**
     * @param  TagOptionDto[]  $tags  All article tags (the tag picker options).
     * @param  ArticleOptionDto[]  $articles  Published articles (the related multi-select options).
     */
    public function __construct(
        public ?ArticleDataDto $article,
        public array $tags,
        public array $articles,
        public ?string $previewUrl,
    ) {
    }
}
