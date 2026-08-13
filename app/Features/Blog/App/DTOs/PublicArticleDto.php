<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use DateTimeImmutable;

/**
 * The public blog article detail (blog.yaml#BlogArticlePageProps —
 * the `ArticleDetailData` shape): the locale-resolved fields, the
 * server-rendered `content_html` (the shared Markdown pipeline), the
 * resolved tags/related cards, the pre-increment view counter and the
 * reaction state (`null` when the `social_reactions` flag is off —
 * no widget). Immutable.
 */
final readonly class PublicArticleDto
{
    /**
     * @param  TagOptionDto[]  $tags
     * @param  TechSkillTag[]  $skillTags
     * @param  ArticleCardDto[]  $related
     * @param  array{user_reaction: string|null, likes: int, dislikes: int}|null  $reactions
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public string $shortDescription,
        public string $contentHtml,
        public ?DateTimeImmutable $publishedAt,
        public int $readingTimeMinutes,
        public ?StoredPicture $leadImage,
        public array $tags,
        public array $skillTags,
        public array $related,
        public int $views,
        public ?array $reactions,
        public BlogSeoDto $seo,
    ) {
    }
}
