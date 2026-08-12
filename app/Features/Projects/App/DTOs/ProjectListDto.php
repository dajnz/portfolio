<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * The public projects list result: the locale-resolved list items,
 * the per-project tech tags and screenshot paths (fetched once for the
 * final row set), the `next_cursor` for the infinite scroll (null =
 * exhaustion), the tag-filter options and the SEO data. Immutable.
 */
final readonly class ProjectListDto
{
    /**
     * @param  ProjectListItemDto[]  $rows
     * @param  array<int, array<int, SkillOptionDto>>  $techTags
     * @param  array<int, array<int, array{image_path: string, alt_text: array<string, string>|null}>>  $screenshots
     * @param  SkillOptionDto[]  $skillTags
     */
    public function __construct(
        public array $rows,
        public array $techTags,
        public array $screenshots,
        public ?PublicPageCursor $nextCursor,
        public array $skillTags,
        public ProjectSeoDto $seo,
    ) {
    }
}
