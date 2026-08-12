<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Mappers;

use App\Features\Projects\Domain\Entities\ProjectEngineeringDetailTranslation;
use App\Features\Projects\Infra\Models\ProjectDetailItemTranslationModel;

/**
 * Converts between `project_detail_item_translations` rows and
 * {@see ProjectEngineeringDetailTranslation} entities.
 */
final class ProjectEngineeringDetailTranslationMapper
{
    public function toEntity(ProjectDetailItemTranslationModel $model): ProjectEngineeringDetailTranslation
    {
        return new ProjectEngineeringDetailTranslation(
            locale: $model->locale,
            title: $model->title,
            description: $model->description,
        );
    }

    /**
     * @return array{
     *     detail_item_id: int,
     *     locale: string,
     *     title: string,
     *     description: string,
     * }
     */
    public function toRow(int $detailItemId, ProjectEngineeringDetailTranslation $translation): array
    {
        return [
            'detail_item_id' => $detailItemId,
            'locale' => $translation->toLocale(),
            'title' => $translation->toTitle(),
            'description' => $translation->toDescription(),
        ];
    }
}
