<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Mappers;

use App\Features\Projects\Domain\Entities\ProjectTranslation;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;

/**
 * Converts between `project_translations` rows and
 * {@see ProjectTranslation} entities.
 */
final class ProjectTranslationMapper
{
    public function toEntity(ProjectTranslationModel $model): ProjectTranslation
    {
        return new ProjectTranslation(
            locale: $model->locale,
            title: $model->title,
            briefDescription: $model->brief_description,
            fullDescription: $model->full_description,
            problem: $model->problem,
            solution: $model->solution,
        );
    }

    /**
     * @return array{
     *     project_id: int,
     *     locale: string,
     *     title: string,
     *     brief_description: string,
     *     full_description: string|null,
     *     problem: string|null,
     *     solution: string|null,
     * }
     */
    public function toRow(int $projectId, ProjectTranslation $translation): array
    {
        return [
            'project_id' => $projectId,
            'locale' => $translation->toLocale(),
            'title' => $translation->toTitle(),
            'brief_description' => $translation->toBriefDescription(),
            'full_description' => $translation->toFullDescription(),
            'problem' => $translation->toProblem(),
            'solution' => $translation->toSolution(),
        ];
    }
}
