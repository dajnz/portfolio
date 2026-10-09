<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Shared\Domain\ValueObjects\BlogArticleId;

/**
 * The project data as submitted by the store/update forms
 * (content-management.md:621-648) — the whole payload of the
 * `StoreProjectUseCase`/`UpdateProjectUseCase`. `submittedSlug` is the
 * raw form value; the server re-resolves it per ADR-0006 (the auto-generated
 * case regenerates with a numeric collision suffix, manual overrides
 * are kept as-is). Composed of the granular data DTOs. Immutable.
 */
final readonly class ProjectDraftDto
{
    /**
     * @param  ProjectScreenshotDto[]  $screenshots
     * @param  ProjectEngineeringDetailDto[]  $engineeringDetails
     * @param  int[]  $techTagIds
     */
    public function __construct(
        public string $submittedSlug,
        public ProjectTranslationsDto $translations,
        public ?ProjectImageDto $preview,
        public array $screenshots = [],
        public array $engineeringDetails = [],
        public ProjectLinksDto $links = new ProjectLinksDto(),
        public bool $isFeatured = false,
        public ?int $featuredSortOrder = null,
        public bool $isPublished = false,
        public array $techTagIds = [],
        public ?BlogArticleId $techWriteupArticleId = null,
    ) {
    }
}
