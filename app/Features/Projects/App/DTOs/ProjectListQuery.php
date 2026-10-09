<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * The public projects list query: the locale, the `date` sort column
 * with the `?direction=asc|desc` variant, the `?tags=` AND-intersect,
 * the optional FTS `?q=` search and the pagination variant — the
 * opaque composite cursor of the infinite scroll or the
 * `page`/`perPage` of the length-aware JSON variant.
 * `includeUnpublished` is the preview bypass.
 */
final readonly class ProjectListQuery
{
    /**
     * @param  int[]  $tagIds
     */
    public function __construct(
        public string $locale,
        public string $sort = 'date',
        public string $sortDirection = 'desc',
        public array $tagIds = [],
        public ?string $search = null,
        public bool $includeUnpublished = false,
        public ?PublicPageCursor $cursor = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {
    }
}
