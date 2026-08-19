<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Mappers;

use App\Features\Resume\Domain\Entities\ResumeConfigTranslation;
use App\Features\Resume\Infra\Models\ResumeConfigTranslationModel;

/**
 * Converts between `resume_config_translations` rows and
 * {@see ResumeConfigTranslation} value objects.
 */
final class ResumeConfigTranslationMapper
{
    public function toEntity(ResumeConfigTranslationModel $model): ResumeConfigTranslation
    {
        return new ResumeConfigTranslation(
            locale: $model->locale,
            aboutText: $model->about_text,
        );
    }

    /**
     * @return array{
     *     resume_config_id: int,
     *     locale: string,
     *     about_text: string|null,
     * }
     */
    public function toRow(ResumeConfigTranslation $translation, int $configId): array
    {
        return [
            'resume_config_id' => $configId,
            'locale' => $translation->toLocale(),
            'about_text' => $translation->toAboutText(),
        ];
    }
}
