<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

use App\Features\Projects\Domain\Entities\Project;
use DateTimeImmutable;

/**
 * The full stored project data — every aggregate part as data DTOs:
 * the identity, the per-locale translations (the editor's LocaleTabs
 * maps), the media (preview + gallery), the engineering details, the
 * links, the state flags, the tech-writeup reference and the resolved
 * tech tags. Returned by the read use cases; the controllers prepare
 * their own output shapes from it. Immutable.
 */
final readonly class ProjectDataDto
{
    /**
     * @param  ProjectScreenshotDto[]  $screenshots
     * @param  ProjectEngineeringDetailDto[]  $engineeringDetails
     * @param  SkillOptionDto[]  $techTags
     */
    public function __construct(
        public int $id,
        public string $slug,
        public ProjectTranslationsDto $translations,
        public ?ProjectImageDto $preview,
        public array $screenshots = [],
        public array $engineeringDetails = [],
        public ProjectLinksDto $links = new ProjectLinksDto(),
        public bool $isFeatured = false,
        public ?int $featuredSortOrder = null,
        public bool $isPublished = false,
        public ?DateTimeImmutable $publishedAt = null,
        public ?int $techWriteupArticleId = null,
        public array $techTags = [],
    ) {
    }

    public static function fromEntity(Project $project): self
    {
        return new self(
            id: $project->toId(),
            slug: $project->toSlug(),
            translations: new ProjectTranslationsDto(
                titles: $project->titlesByLocale(),
                briefDescriptions: $project->briefDescriptionsByLocale(),
                fullDescriptions: $project->fullDescriptionsByLocale(),
                problems: $project->problemsByLocale(),
                solutions: $project->solutionsByLocale(),
            ),
            preview: $project->toPreviewPicture() === null
                ? null
                : new ProjectImageDto(
                    url: $project->toPreviewPicture()?->url,
                    altLabels: $project->toPreviewPicture()?->altLabelsMap() ?? [],
                ),
            screenshots: array_map(
                static fn ($screenshot): ProjectScreenshotDto => new ProjectScreenshotDto(
                    id: $screenshot->toId(),
                    image: new ProjectImageDto(
                        url: $screenshot->toImagePath(),
                        altLabels: $screenshot->altLabelsByLocale(),
                        descriptions: $screenshot->descriptionsByLocale(),
                    ),
                    titles: $screenshot->titlesByLocale(),
                    sortOrder: $screenshot->toSortOrder()->toValue(),
                ),
                $project->toScreenshots(),
            ),
            engineeringDetails: array_map(
                static fn ($item): ProjectEngineeringDetailDto => new ProjectEngineeringDetailDto(
                    id: $item->toId(),
                    icon: $item->toIcon(),
                    titles: $item->titlesByLocale(),
                    descriptions: $item->descriptionsByLocale(),
                ),
                $project->toEngineeringDetails(),
            ),
            links: new ProjectLinksDto(
                sourceCodeUrl: $project->toSourceCodeUrl(),
                liveDemoUrl: $project->toLiveDemoUrl(),
            ),
            isFeatured: $project->toIsFeatured(),
            featuredSortOrder: $project->toFeaturedSortOrder(),
            isPublished: $project->toIsPublished(),
            publishedAt: $project->toPublishedAt(),
            techWriteupArticleId: $project->toTechWriteupArticleId(),
            techTags: array_map(
                static fn ($tag): SkillOptionDto => new SkillOptionDto(
                    id: $tag->toId(),
                    name: $tag->toSkillName(),
                    icon: $tag->toIcon(),
                ),
                $project->toTechTags(),
            ),
        );
    }
}
