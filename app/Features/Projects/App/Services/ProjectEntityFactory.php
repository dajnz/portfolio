<?php

declare(strict_types=1);

namespace App\Features\Projects\App\Services;

use App\Features\Projects\App\DTOs\ProjectDraftDto;
use App\Features\Projects\Domain\Entities\Project;
use App\Features\Projects\Domain\Entities\ProjectEngineeringDetail;
use App\Features\Projects\Domain\Entities\ProjectEngineeringDetailTranslation;
use App\Features\Projects\Domain\Entities\ProjectScreenshot;
use App\Features\Projects\Domain\Entities\ProjectTranslation;
use App\Features\Projects\Domain\ValueObjects\EngineeringDetailIcon;
use App\Features\Projects\Domain\ValueObjects\FeaturedState;
use App\Features\Projects\Domain\ValueObjects\ProjectId;
use App\Features\Projects\Domain\ValueObjects\ProjectLinks;
use App\Features\Projects\Domain\ValueObjects\ProjectPictures;
use App\Features\Projects\Domain\ValueObjects\ProjectSlugs;
use App\Features\Projects\Domain\ValueObjects\PublishedState;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\Slug;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;

/**
 * Builds {@see Project} aggregates from the app-layer draft DTO — shared
 * by the store/update use cases. All value normalization (per-locale
 * maps, the reconcile-by-id convention A2, the zero-indexed sort
 * orders, the preview picture, the featured state) lives here; the use
 * cases only resolve the slug, decide the publication state and
 * delegate persistence.
 */
final class ProjectEntityFactory
{
    public function __construct(
        private readonly TechSkillReadRepositoryInterface $skills,
    ) {
    }

    public function make(
        ProjectId $id,
        Slug $slug,
        PublishedState $published,
        ProjectDraftDto $draft,
    ): Project {
        return new Project(
            id: $id,
            slugs: new ProjectSlugs($slug),
            pictures: $this->pictures($draft),
            links: new ProjectLinks(
                sourceCodeUrl: $draft->links->sourceCodeUrl,
                liveDemoUrl: $draft->links->liveDemoUrl,
            ),
            techWriteupArticleId: $draft->techWriteupArticleId,
            featured: new FeaturedState(
                isFeatured: $draft->isFeatured,
                featuredSortOrder: $draft->featuredSortOrder === null
                    ? null
                    : new SortOrder($draft->featuredSortOrder),
            ),
            published: $published,
            translations: $this->translations($draft->translations),
            techTags: $this->techTags($draft->techTagIds),
            engineeringDetails: $this->engineeringDetails($draft->engineeringDetails),
        );
    }

    /**
     * @return ProjectTranslation[]
     */
    private function translations(\App\Features\Projects\App\DTOs\ProjectTranslationsDto $translations): array
    {
        $entities = [];

        foreach ($translations->titles as $locale => $title) {
            if (! is_string($title) || $title === '') {
                continue;
            }

            $entities[] = new ProjectTranslation(
                locale: (string) $locale,
                title: $title,
                briefDescription: $this->nullableString($translations->briefDescriptions[$locale] ?? null) ?? '',
                fullDescription: $this->nullableString($translations->fullDescriptions[$locale] ?? null),
                problem: $this->nullableString($translations->problems[$locale] ?? null),
                solution: $this->nullableString($translations->solutions[$locale] ?? null),
            );
        }

        return $entities;
    }

    /**
     * Resolves the submitted skill IDs into their light tags (the
     * entity's aggregate carries the names/icons for display). The
     * `exists:skills,id` request rule guarantees they exist.
     *
     * @param  int[]  $techTagIds
     * @return TechSkillTag[]
     */
    private function techTags(array $techTagIds): array
    {
        return array_map(
            static fn ($skill): TechSkillTag => new TechSkillTag(
                id: $skill->toId(),
                skillName: $skill->toName(),
                icon: $skill->toIcon() === null ? null : new SkillIcon($skill->toIcon()),
            ),
            $this->skills->findByIds($techTagIds, app()->getLocale()),
        );
    }

    /**
     * The preview picture (from the draft's image DTO) and the gallery
     * rows in their submitted order — the position in the array is the
     * `sort_order`; an existing `id` marks an update (A2).
     */
    private function pictures(ProjectDraftDto $draft): ProjectPictures
    {
        $screenshots = [];

        foreach (array_values($draft->screenshots) as $position => $screenshot) {
            $url = $screenshot->image->url;

            if (! is_string($url) || $url === '') {
                continue;
            }

            $screenshots[] = new ProjectScreenshot(
                id: $screenshot->id ?? 0,
                picture: new StoredPicture(
                    url: $url,
                    altLabels: $this->nonEmptyLocaleMap($screenshot->image->altLabels),
                    descriptions: $this->nonEmptyLocaleMap($screenshot->image->descriptions),
                ),
                titles: $this->nonEmptyLocaleMap($screenshot->titles),
                sortOrder: new SortOrder($position),
            );
        }

        return new ProjectPictures(
            previewPicture: $this->previewPicture($draft->preview),
            screenshots: $screenshots,
        );
    }

    /**
     * @param  array<int, \App\Features\Projects\App\DTOs\ProjectEngineeringDetailDto>  $details
     * @return ProjectEngineeringDetail[]
     */
    private function engineeringDetails(array $details): array
    {
        $entities = [];

        foreach (array_values($details) as $position => $item) {
            $titles = $this->nonEmptyLocaleMap($item->titles);

            if ($titles === []) {
                continue;
            }

            $translations = [];
            foreach ($titles as $locale => $title) {
                $description = $this->nullableString($item->descriptions[$locale] ?? null);

                if ($description === null) {
                    continue;
                }

                $translations[] = new ProjectEngineeringDetailTranslation(
                    locale: $locale,
                    title: $title,
                    description: $description,
                );
            }

            if ($translations === []) {
                continue;
            }

            $entities[] = new ProjectEngineeringDetail(
                id: $item->id ?? 0,
                icon: $item->icon === null ? null : new EngineeringDetailIcon($item->icon),
                sortOrder: new SortOrder($position),
                translations: $translations,
            );
        }

        return $entities;
    }

    private function previewPicture(?\App\Features\Projects\App\DTOs\ProjectImageDto $preview): ?StoredPicture
    {
        if ($preview === null || ! is_string($preview->url) || $preview->url === '') {
            return null;
        }

        return new StoredPicture(
            url: $preview->url,
            altLabels: $this->nonEmptyLocaleMap($preview->altLabels),
        );
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, string>
     */
    private function nonEmptyLocaleMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $locale => $text) {
            if (is_string($text) && $text !== '') {
                $normalized[(string) $locale] = $text;
            }
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
