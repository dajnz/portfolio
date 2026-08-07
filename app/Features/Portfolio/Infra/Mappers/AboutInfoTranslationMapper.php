<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Mappers;

use App\Features\Portfolio\Domain\Entities\AboutInfoTranslation;
use App\Features\Portfolio\Infra\Models\AboutInfoTranslationModel;

/**
 * Converts between `about_info_translations` rows and
 * {@see AboutInfoTranslation} entities.
 */
final class AboutInfoTranslationMapper
{
    public function toEntity(AboutInfoTranslationModel $model): AboutInfoTranslation
    {
        return new AboutInfoTranslation(
            locale: $model->locale,
            name: $model->name,
            title: $model->title,
            shortPitch: $model->short_pitch,
            location: $model->location,
        );
    }

    /**
     * @return array{
     *     about_info_id: int,
     *     locale: string,
     *     name: string,
     *     title: string,
     *     short_pitch: string,
     *     location: string|null,
     * }
     */
    public function toRow(AboutInfoTranslation $translation, int $aboutInfoId): array
    {
        return [
            'about_info_id' => $aboutInfoId,
            'locale' => $translation->toLocale(),
            'name' => $translation->toName(),
            'title' => $translation->toTitle(),
            'short_pitch' => $translation->toShortPitch(),
            'location' => $translation->toLocation(),
        ];
    }
}
