<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\App\DTOs\PaginationDto;

/**
 * The JSON dual-response variant of the public blog index
 * (routes.md:199-212 — `BlogIndexResponse`): the raw-value rows plus
 * the length-aware pagination. Immutable.
 */
final readonly class PaginatedPublicBlogListDto
{
    /**
     * @param  ArticleCardDto[]  $articles
     */
    public function __construct(
        public array $articles,
        public PaginationDto $pagination,
    ) {
    }
}
