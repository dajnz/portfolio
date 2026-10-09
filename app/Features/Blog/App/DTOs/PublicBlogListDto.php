<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * The public blog index page data (blog.yaml#BlogIndexPageProps): the
 * card rows, the encoded cursor position for "Load More" (null when
 * the final batch is incomplete — the exhaustion signal), the tag
 * filter options, the distinct publication years, the published-
 * projects signal (the ADR-0025 nav condition — ADR-0004) and the SEO data.
 * Immutable.
 */
final readonly class PublicBlogListDto
{
    /**
     * @param  ArticleCardDto[]  $articles
     * @param  TagOptionDto[]  $tags
     * @param  int[]  $years
     */
    public function __construct(
        public array $articles,
        public ?PublicPageCursor $nextCursor,
        public array $tags,
        public array $years,
        public BlogSeoDto $seo,
        public bool $hasProjects = false,
    ) {
    }
}
