<?php

declare(strict_types=1);

namespace App\Features\Experience\Infra\Mappers;

use App\Features\Experience\Domain\Entities\TimelineItemTranslation;
use App\Features\Experience\Infra\Models\TimelineItemTranslationModel;

/**
 * Converts between `experience_timeline_item_translations` rows and
 * {@see TimelineItemTranslation} value objects.
 */
final class TimelineItemTranslationMapper
{
    public function toEntity(TimelineItemTranslationModel $model): TimelineItemTranslation
    {
        return new TimelineItemTranslation(
            locale: $model->locale,
            position: $model->position ?? '',
            companyName: $model->company_name ?? '',
            companyCountry: $model->company_country,
            shortDescription: $model->short_description ?? '',
            achievements: $model->achievements ?? [],
        );
    }

    /**
     * @return array{
     *     item_id: int,
     *     locale: string,
     *     position: string,
     *     company_name: string,
     *     company_country: string|null,
     *     short_description: string|null,
     *     achievements: string|null,
     * }
     */
    public function toRow(TimelineItemTranslation $translation, int $itemId): array
    {
        return [
            'item_id' => $itemId,
            'locale' => $translation->toLocale(),
            'position' => $translation->toPosition(),
            'company_name' => $translation->toCompanyName(),
            'company_country' => $translation->toCompanyCountry(),
            'short_description' => $translation->toShortDescription(),
            // The translation sync uses a query-builder upsert, which
            // bypasses the model's array cast — encode explicitly.
            'achievements' => $translation->toAchievements() === []
                ? null
                : json_encode($translation->toAchievements(), JSON_THROW_ON_ERROR),
        ];
    }
}
