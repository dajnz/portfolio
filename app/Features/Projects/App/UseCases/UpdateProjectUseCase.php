<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\DTOs\ProjectDraftDto;
use App\Features\Projects\App\Services\ProjectEntityFactory;
use App\Features\Projects\App\Services\ProjectSlugService;
use App\Features\Projects\Domain\Entities\Project;
use App\Features\Projects\Domain\Exceptions\ProjectNotFoundException;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Projects\Domain\ValueObjects\ProjectId;
use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use DateTimeImmutable;

/**
 * Updates a project: the slug is re-resolved per ADR-0006 — a submitted slug
 * equal to the stored one (or to the naive auto-generation of the EN
 * title) is regenerated server-side with a numeric collision suffix, a
 * differing submission is a manual override kept as-is; the rest of
 * the payload replaces the aggregate parts via the repository's
 * reconcile (ADR-0005). The publication state is derived from the EXISTING
 * aggregate — `published_at` is immutable after the first publish
 * (database.md:210): a first form-publish runs
 * {@see Project::publish()} (invariants asserted), an unpublish keeps
 * the timestamp. A slug change records the old slug in `old_slugs`
 * (ADR-0006). The obsolete screenshot files (replaced/removed gallery rows)
 * are deleted via the shared asset cleaner after the save — the
 * repository stays persistence-only. The repository persists the
 * aggregate — transaction included — in one call.
 */
final class UpdateProjectUseCase
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
        private readonly ProjectEntityFactory $factory,
        private readonly ProjectSlugService $slugs,
        private readonly AssetCleanerInterface $assets,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function update(int $id, ProjectDraftDto $draft): void
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw ProjectNotFoundException::forId($id);
        }

        $slug = $this->slugs->resolve(
            $draft->submittedSlug,
            $existing->toSlug(),
            $draft->translations->enTitle(),
            $id,
        );

        $project = $this->factory->make(
            id: new ProjectId($id),
            slug: $slug,
            published: $existing->toPublishedState(),
            draft: $draft,
        );

        if ($draft->isPublished && ! $existing->toPublishedState()->toIsPublished()) {
            $project->publish(new DateTimeImmutable('now'));
        } elseif (! $draft->isPublished) {
            $project->unpublish();
        }

        $this->repository->save($project);
        $this->assets->delete($this->obsoleteScreenshotPaths($existing, $draft));

        $this->countCache->invalidate('projects');
    }

    /**
     * The RustFS files made obsolete by the screenshot reconcile (ADR-0005):
     * the images of gallery rows removed from the submitted set and
     * the replaced images of rows whose path changed
     * (content-management.md:813-819).
     *
     * @return string[]
     */
    private function obsoleteScreenshotPaths(Project $existing, ProjectDraftDto $draft): array
    {
        $newPathById = [];
        foreach ($draft->screenshots as $screenshot) {
            if ($screenshot->id !== null && is_string($screenshot->image->url)) {
                $newPathById[$screenshot->id] = $screenshot->image->url;
            }
        }

        $paths = [];
        foreach ($existing->toScreenshots() as $screenshot) {
            $newPath = $newPathById[$screenshot->toId()] ?? null;

            if ($newPath === null || $newPath !== $screenshot->toImagePath()) {
                $paths[] = $screenshot->toImagePath();
            }
        }

        return $paths;
    }
}
