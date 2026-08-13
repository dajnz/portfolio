<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Mappers;

use App\Features\Projects\Domain\Entities\Project;
use App\Features\Projects\Domain\Entities\ProjectEngineeringDetail;
use App\Features\Projects\Domain\Entities\ProjectScreenshot;
use App\Features\Projects\Domain\Entities\ProjectTranslation;
use App\Features\Projects\Domain\ValueObjects\FeaturedState;
use App\Features\Projects\Domain\ValueObjects\ProjectId;
use App\Features\Projects\Domain\ValueObjects\ProjectLinks;
use App\Features\Projects\Domain\ValueObjects\ProjectPictures;
use App\Features\Projects\Domain\ValueObjects\ProjectSlugs;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\BlogArticleId;
use App\Features\Shared\Domain\ValueObjects\Slug;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use DateTimeImmutable;

/**
 * Converts between `projects` rows (with their translations,
 * screenshots and detail items pre-loaded via the relations and the
 * tech-tag rows passed separately) and {@see Project} entities, and
 * back to the base-row data. The model's Carbon dates are converted to
 * `DateTimeImmutable` at the boundary — the domain stays Carbon-free.
 */
final class ProjectMapper
{
    public function __construct(
        private readonly ProjectTranslationMapper $translationMapper,
        private readonly ProjectScreenshotMapper $screenshotMapper,
        private readonly ProjectEngineeringDetailMapper $engineeringDetailMapper,
    ) {
    }

    /**
     * @param  TechSkillTag[]  $techTags  Tags from the `project_tech_tags` ↔ `skills` join.
     */
    public function toEntity(ProjectModel $model, array $techTags = []): Project
    {
        $translations = $model->relationLoaded('translations')
            ? $model->translations
            : $model->translations()->get();

        $screenshots = $model->relationLoaded('screenshots')
            ? $model->screenshots
            : $model->screenshots()->get();

        $detailItems = $model->relationLoaded('detailItems')
            ? $model->detailItems
            : $model->detailItems()->get();

        return new Project(
            id: new ProjectId((int) $model->id),
            slugs: new ProjectSlugs(new Slug($model->slug)),
            pictures: new ProjectPictures(
                previewPicture: $this->previewPicture($model),
                screenshots: $screenshots
                    ->map(fn (mixed $row): ProjectScreenshot => $this->screenshotMapper->toEntity($row))
                    ->all(),
            ),
            links: new ProjectLinks(
                sourceCodeUrl: $model->github_url,
                liveDemoUrl: $model->live_demo_url,
            ),
            techWriteupArticleId: $model->tech_writeup_article_id === null
                ? null
                : new BlogArticleId((int) $model->tech_writeup_article_id),
            featured: new FeaturedState(
                isFeatured: (bool) $model->is_featured,
                featuredSortOrder: $model->featured_sort_order === null
                    ? null
                    : new SortOrder((int) $model->featured_sort_order),
            ),
            published: new PublishedState(
                isPublished: (bool) $model->is_published,
                publishedAt: $model->published_at === null
                    ? null
                    : $this->toImmutable($model->published_at),
            ),
            translations: $translations
                ->map(fn (mixed $row): ProjectTranslation => $this->translationMapper->toEntity($row))
                ->all(),
            techTags: $techTags,
            engineeringDetails: $detailItems
                ->map(fn (mixed $row): ProjectEngineeringDetail => $this->engineeringDetailMapper->toEntity($row))
                ->all(),
        );
    }

    /**
     * @return array{
     *     slug: string,
     *     preview_image: string|null,
     *     preview_image_alt: array<string, string>|null,
     *     github_url: string|null,
     *     live_demo_url: string|null,
     *     tech_writeup_article_id: int|null,
     *     is_featured: bool,
     *     featured_sort_order: int|null,
     *     is_published: bool,
     *     published_at: string|null,
     * }
     */
    public function toRow(Project $project): array
    {
        $preview = $project->toPreviewPicture();

        return [
            'slug' => $project->toSlug(),
            'preview_image' => $preview?->url,
            'preview_image_alt' => $preview === null || $preview->altLabelsMap() === []
                ? null
                : $preview->altLabelsMap(),
            'github_url' => $project->toSourceCodeUrl(),
            'live_demo_url' => $project->toLiveDemoUrl(),
            'tech_writeup_article_id' => $project->toTechWriteupArticleId(),
            'is_featured' => $project->toIsFeatured(),
            'featured_sort_order' => $project->toFeaturedSortOrder(),
            'is_published' => $project->toIsPublished(),
            'published_at' => $project->toPublishedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function previewPicture(ProjectModel $model): ?StoredPicture
    {
        if ($model->preview_image === null) {
            return null;
        }

        $altLabels = [];
        foreach ((array) ($model->preview_image_alt ?? []) as $locale => $alt) {
            if (is_string($alt) && $alt !== '') {
                $altLabels[(string) $locale] = $alt;
            }
        }

        return new StoredPicture(
            url: $model->preview_image,
            altLabels: $altLabels,
        );
    }

    private function toImmutable(mixed $date): DateTimeImmutable
    {
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        return new DateTimeImmutable($date->toDateTimeString());
    }
}
