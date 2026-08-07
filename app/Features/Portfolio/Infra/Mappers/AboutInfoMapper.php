<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Mappers;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\Entities\AboutInfoTranslation;
use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Portfolio\Infra\Models\AboutInfoModel;

/**
 * Converts between the single `about_info` row (with its translation
 * rows pre-loaded via the `translations` relation) and the
 * {@see AboutInfo} entity, and back to the row data.
 */
final class AboutInfoMapper
{
    public function __construct(
        private readonly AboutInfoTranslationMapper $translationMapper,
    ) {
    }

    public function toEntity(AboutInfoModel $model): AboutInfo
    {
        $translations = $model->relationLoaded('translations')
            ? $model->translations
            : $model->translations()->get();

        return new AboutInfo(
            id: $model->id,
            photo: $model->photo,
            photoAlt: $model->photo_alt ?? [],
            showLocation: $model->show_location,
            timezone: $model->timezone,
            relocationReadiness: $model->relocation_readiness,
            availabilityStatus: $model->availability_status === null
                ? null
                : AvailabilityStatus::from($model->availability_status),
            workType: $model->work_type,
            translations: $translations
                ->map(fn (mixed $row): AboutInfoTranslation => $this->translationMapper->toEntity($row))
                ->all(),
        );
    }

    /**
     * @return array{
     *     photo: string|null,
     *     photo_alt: array<string, string|null>,
     *     show_location: bool,
     *     timezone: string|null,
     *     relocation_readiness: bool,
     *     availability_status: string|null,
     *     work_type: string,
     * }
     */
    public function toRow(AboutInfo $aboutInfo): array
    {
        return [
            'photo' => $aboutInfo->toPhoto(),
            'photo_alt' => $aboutInfo->toPhotoAlt(),
            'show_location' => $aboutInfo->toShowLocation(),
            'timezone' => $aboutInfo->toTimezone(),
            'relocation_readiness' => $aboutInfo->toRelocationReadiness(),
            'availability_status' => $aboutInfo->toAvailabilityStatus()?->value,
            'work_type' => $aboutInfo->toWorkType(),
        ];
    }
}
