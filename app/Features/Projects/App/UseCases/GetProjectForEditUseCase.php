<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\ProjectDataDto;
use App\Features\Projects\App\DTOs\ProjectEditorDataDto;
use App\Features\Projects\App\DTOs\SkillOptionDto;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Shared\App\Contracts\PreviewUrlGeneratorInterface;
use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * The project editor data (the create/edit form, projects.yaml#
 * ProjectFormPageProps): the stored project data (null when creating —
 * hydrated with every aggregate part for the tabbed editor and mapped
 * to the data DTOs here), the tech-skill picker options and the signed
 * 1-hour preview URL (routes.md:151 — the preview button opens it in a
 * new tab; null on the create form). Trashed projects reach the form
 * too (restore happens from the form's own view, B29).
 */
final class GetProjectForEditUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly TechSkillReadRepositoryInterface $skills,
        private readonly PreviewUrlGeneratorInterface $previewUrls,
    ) {
    }

    public function get(?int $id, string $locale): ProjectEditorDataDto
    {
        $project = $id === null
            ? null
            : $this->repository->findByIdIncludingTrashed($id);

        return new ProjectEditorDataDto(
            project: $project === null
                ? null
                : ProjectDataDto::fromEntity($project),
            skills: array_map(
                static fn (TechSkill $skill): SkillOptionDto => new SkillOptionDto(
                    id: $skill->toId(),
                    name: $skill->toName(),
                    icon: $skill->toIcon(),
                ),
                $this->skills->findAll($locale),
            ),
            previewUrl: $id === null
                ? null
                : $this->previewUrls->temporarySigned('projects.preview', 3600, ['project' => $id]),
        );
    }
}
