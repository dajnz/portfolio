<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Mappers;

use App\Features\Projects\Domain\Entities\ProjectEngineeringDetail;
use App\Features\Projects\Domain\Entities\ProjectEngineeringDetailTranslation;
use App\Features\Projects\Infra\Models\ProjectDetailItemModel;
use App\Features\Projects\Domain\ValueObjects\EngineeringDetailIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;

/**
 * Converts between `project_detail_items` rows (with their translation
 * rows pre-loaded via the `translations` relation) and
 * {@see ProjectEngineeringDetail} entities, and back to the row data.
 */
final class ProjectEngineeringDetailMapper
{
    public function __construct(
        private readonly ProjectEngineeringDetailTranslationMapper $translationMapper,
    ) {
    }

    public function toEntity(ProjectDetailItemModel $model): ProjectEngineeringDetail
    {
        $translations = $model->relationLoaded('translations')
            ? $model->translations
            : $model->translations()->get();

        return new ProjectEngineeringDetail(
            id: (int) $model->id,
            icon: $model->icon === null ? null : new EngineeringDetailIcon($model->icon),
            sortOrder: new SortOrder((int) $model->sort_order),
            translations: $translations
                ->map(fn (mixed $row): ProjectEngineeringDetailTranslation => $this->translationMapper->toEntity($row))
                ->all(),
        );
    }

    /**
     * @return array{
     *     project_id: int,
     *     icon: string|null,
     *     sort_order: int,
     * }
     */
    public function toRow(int $projectId, ProjectEngineeringDetail $item): array
    {
        return [
            'project_id' => $projectId,
            'icon' => $item->toIcon(),
            'sort_order' => $item->toSortOrder()->toValue(),
        ];
    }
}
