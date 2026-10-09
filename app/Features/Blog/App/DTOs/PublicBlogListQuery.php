<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * The public blog index request — the app-layer input of
 * {@see \App\Features\Blog\App\UseCases\GetPublicBlogIndexUseCase}:
 * the request locale, the `?article_tags=` / `?skill_tags=` ids
 * (ADR-0004 — separate params avoid id collisions), the `?year=` filter,
 * the FTS `?q=` search and the opaque infinite-scroll cursor. The
 * `page`/`perPage` fields drive the JSON dual-response variant only.
 * Immutable.
 */
final readonly class PublicBlogListQuery
{
    /**
     * @param  int[]  $articleTagIds
     * @param  int[]  $skillTagIds
     */
    public function __construct(
        public string $locale,
        public array $articleTagIds = [],
        public array $skillTagIds = [],
        public ?int $year = null,
        public ?string $search = null,
        public ?PublicPageCursor $cursor = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {
    }
}
