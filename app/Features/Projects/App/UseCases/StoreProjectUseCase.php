<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\ProjectDraftDto;
use App\Features\Projects\App\Services\ProjectEntityFactory;
use App\Features\Projects\App\Services\ProjectSlugService;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Projects\Domain\ValueObjects\ProjectId;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Projects\App\Contracts\ScreenshotThumbnailSchedulerInterface;
use DateTimeImmutable;

/**
 * Creates a project: the resolved slug (auto-generated from the EN
 * title with a collision suffix, or the manual override kept as-is),
 * the shared fields, the per-locale translations (title + brief
 * description required per ACTIVE locale), the preview picture, the
 * screenshot gallery and the engineering detail items, the tech-tag
 * pivot, the featured state and the optional tech-writeup link. A
 * form-save with `is_published` set publishes the fresh aggregate
 * ({@see \App\Features\Projects\Domain\Entities\Project::publish()}
 * asserts the publication invariants). The repository persists the
 * aggregate in one transaction. Returns the created project's id so
 * the controller can redirect to its edit page (a create must not
 * leave the form in create mode — a second save would duplicate the
 * row).
 */
final class StoreProjectUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly ProjectEntityFactory $factory,
        private readonly ProjectSlugService $slugs,
        private readonly CountCacheInvalidatorInterface $countCache,
        private readonly ScreenshotThumbnailSchedulerInterface $thumbnails,
    ) {
    }

    public function store(ProjectDraftDto $draft): ProjectId
    {
        $slug = $this->slugs->resolve(
            $draft->submittedSlug,
            null,
            $draft->translations->enTitle(),
            null,
        );

        $project = $this->factory->make(
            id: new ProjectId(0),
            slug: $slug,
            published: PublishedState::unpublished(),
            draft: $draft,
        );

        if ($draft->isPublished) {
            $project->publish(new DateTimeImmutable('now'));
        }

        $projectId = $this->repository->save($project);

        $this->scheduleThumbnails($projectId);
        $this->countCache->invalidate('projects');

        return new ProjectId($projectId);
    }

    private function scheduleThumbnails(int $projectId): void
    {
        $created = $this->repository->findById($projectId);

        if ($created === null) {
            return;
        }

        $this->thumbnails->schedule(
            array_map(
                static fn ($screenshot): int => $screenshot->toId(),
                $created->toScreenshots(),
            ),
        );
    }
}
