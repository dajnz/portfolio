<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Mappers;

use App\Features\Resume\Domain\Entities\ResumeConfig;
use App\Features\Resume\Domain\Entities\ResumeConfigTranslation;
use App\Features\Resume\Domain\ValueObjects\ResumeMode;
use App\Features\Resume\Infra\Models\ResumeConfigModel;
use App\Features\Resume\Infra\Models\ResumeConfigTranslationModel;
use InvalidArgumentException;

/**
 * Converts between the `resume_config` (+ translation) rows and the
 * {@see ResumeConfig} aggregate (mappers build entities from raw model
 * rows — the entities validate by construction, so a malformed row
 * surfaces as an {@see InvalidArgumentException}).
 */
final class ResumeConfigMapper
{
    public function toEntity(ResumeConfigModel $model): ResumeConfig
    {
        return new ResumeConfig(
            id: (int) $model->id,
            mode: ResumeMode::from((string) $model->mode),
            layout: $model->layout ?? [],
            customResumeFile: $model->custom_resume_file,
            translations: $model->translations
                ->map(fn (ResumeConfigTranslationModel $translation): ResumeConfigTranslation => new ResumeConfigTranslation(
                    locale: $translation->locale,
                    aboutText: $translation->about_text,
                ))
                ->all(),
            updatedAt: $model->updated_at->toDateTimeImmutable(),
        );
    }

    /**
     * @return array{
     *     mode: string,
     *     layout: array<string, mixed>,
     *     custom_resume_file: string|null,
     * }
     */
    public function toRow(ResumeConfig $config): array
    {
        return [
            'mode' => $config->toMode()->value,
            'layout' => $config->toLayout(),
            'custom_resume_file' => $config->toCustomResumeFile(),
        ];
    }
}
