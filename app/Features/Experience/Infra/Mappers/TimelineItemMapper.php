<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Mappers;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\Entities\TimelineItemTranslation;
use App\Features\Experience\Domain\ValueObjects\RelatedProject;
use App\Features\Experience\Domain\ValueObjects\WorkPeriod;
use App\Features\Experience\Infra\Models\TimelineItemModel;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use DateTimeImmutable;

/**
 * Converts between `experience_timeline_items` rows (with their
 * translation rows pre-loaded via the `translations` relation and the
 * tech-tag rows passed separately) and {@see TimelineItem} entities,
 * and back to the row data. The model's Carbon dates are converted to
 * `DateTimeImmutable` at the boundary — the domain stays Carbon-free.
 */
final class TimelineItemMapper
{
    public function __construct(
        private readonly TimelineItemTranslationMapper $translationMapper,
    ) {
    }

    /**
     * @param  TechSkillTag[]  $techTags  Tags from the `experience_item_tech_tags` ↔ `skills` join.
     */
    public function toEntity(TimelineItemModel $model, array $techTags = []): TimelineItem
    {
        $translations = $model->relationLoaded('translations')
            ? $model->translations
            : $model->translations()->get();

        return new TimelineItem(
            id: (int) $model->id,
            period: new WorkPeriod(
                startedAt: $this->toImmutable($model->started_at),
                endedAt: $model->ended_at === null
                    ? null
                    : $this->toImmutable($model->ended_at),
            ),
            isPublished: (bool) $model->is_published,
            sortOrder: new SortOrder((int) $model->sort_order),
            relatedProject: $this->relatedProject($model),
            includeInResume: (bool) $model->include_in_resume,
            translations: $translations
                ->map(fn (mixed $row): TimelineItemTranslation => $this->translationMapper->toEntity($row))
                ->all(),
            techTags: $techTags,
        );
    }

    /**
     * @return array{
     *     started_at: string,
     *     ended_at: string|null,
     *     is_published: bool,
     *     sort_order: int,
     *     project_id: int|null,
     *     include_in_resume: bool,
     *     external_project_name: string|null,
     *     external_project_url: string|null,
     * }
     */
    public function toRow(TimelineItem $item): array
    {
        $period = $item->toPeriod();

        return [
            'started_at' => $period->toStartedAt()->format('Y-m-d'),
            'ended_at' => $period->toEndedAt()?->format('Y-m-d'),
            'is_published' => $item->toIsPublished(),
            'sort_order' => $item->toSortOrder()->toValue(),
            'project_id' => $item->toRelatedProject()?->toOwnProjectId(),
            'include_in_resume' => $item->toIncludeInResume(),
            'external_project_name' => $item->toRelatedProject()?->toExternalProjectName(),
            'external_project_url' => $item->toRelatedProject()?->toExternalProjectUrl(),
        ];
    }

    private function relatedProject(TimelineItemModel $model): ?RelatedProject
    {
        if (
            $model->project_id === null
            && $model->external_project_name === null
            && $model->external_project_url === null
        ) {
            return null;
        }

        return new RelatedProject(
            ownProjectId: $model->project_id === null ? null : (int) $model->project_id,
            externalProjectName: $model->external_project_name,
            externalProjectUrl: $model->external_project_url,
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
