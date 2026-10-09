<?php

declare(strict_types=1);

namespace App\Features\Experience\App\UseCases;

use App\Features\Experience\Domain\Entities\TimelineItem;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Experience\Infra\Repositories\TimelineItemListQueryRepositoryInterface;
use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * The experience form page data: the existing item (null when creating)
 * plus the picker options — all tech skills for the `AdminTechSkillPicker`
 * and the non-trashed portfolio projects (locale-resolved titles) for the
 * searchable `ProjectSearchCombobox`.
 */
final class GetTimelineItemForEditUseCase
{
    public function __construct(
        private readonly TimelineItemRepositoryInterface $repository,
        private readonly TimelineItemListQueryRepositoryInterface $listQueryRepository,
        private readonly TechSkillReadRepositoryInterface $skills,
    ) {
    }

    /**
     * @return array{
     *     item: TimelineItem|null,
     *     projects: array<int, array{id: int, title: string, slug: string}>,
     *     skills: TechSkill[],
     * }
     */
    public function get(?int $id, string $locale): array
    {
        return [
            'item' => $id === null ? null : $this->repository->findById($id),
            'projects' => $this->listQueryRepository->projectsForOptions($locale),
            'skills' => $this->skills->findAll($locale),
        ];
    }
}
