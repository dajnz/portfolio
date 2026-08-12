<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\ProjectDraftDto;
use App\Features\Projects\App\Services\ProjectEntityFactory;
use App\Features\Projects\App\Services\ProjectSlugService;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Projects\Domain\ValueObjects\ProjectId;
use App\Features\Projects\Domain\ValueObjects\PublishedState;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use DateTimeImmutable;

/**
 * Creates a project (content-management.md:135-162): the resolved slug
 * (A3 — auto-generated from the EN title with a collision suffix, or
 * the manual override kept as-is), the shared fields, the per-locale
 * translations (title + brief description required per ACTIVE locale),
 * the preview picture, the screenshot gallery and the engineering
 * detail items, the tech-tag pivot, the featured state and the
 * optional tech-writeup link. A form-save with `is_published` set
 * publishes the fresh aggregate ({@see \App\Features\Projects\Domain\Entities\Project::publish()}
 * asserts the publication invariants). The repository persists the
 * aggregate in one transaction.
 */
final class StoreProjectUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly ProjectEntityFactory $factory,
        private readonly ProjectSlugService $slugs,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function store(ProjectDraftDto $draft): void
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

        $this->repository->save($project);

        $this->countCache->invalidate('projects');
    }
}
