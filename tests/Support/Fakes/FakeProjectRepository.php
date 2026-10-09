<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Projects\Domain\Entities\Project;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;

/**
 * In-memory {@see ProjectRepositoryInterface} for the use-case unit
 * tests: the slug-related reads are configurable (the restore
 * uniqueness re-check — ADR-0006), the write methods record their calls.
 */
final class FakeProjectRepository implements ProjectRepositoryInterface
{
    /**
     * @param  array<int, string>  $trashedSlugs  Project id => slug (soft-deleted).
     * @param  string[]  $activeSlugs  Slugs occupied by non-trashed projects.
     */
    public function __construct(
        public array $trashedSlugs = [],
        public array $activeSlugs = [],
    ) {
    }

    public array $restoredIds = [];

    public array $restoredManyIds = [];

    public function findById(int $id): ?Project
    {
        return null;
    }

    public function findByIdIncludingTrashed(int $id): ?Project
    {
        return null;
    }

    public function findBySlug(string $slug): ?Project
    {
        return null;
    }

    public function findBySlugIncludingUnpublished(string $slug): ?Project
    {
        return null;
    }

    public function slugsOfProjects(array $ids): array
    {
        return array_intersect_key($this->trashedSlugs, array_flip($ids));
    }

    public function slugsOccupiedByActive(array $slugs): array
    {
        return array_values(array_intersect($slugs, $this->activeSlugs));
    }

    public function takenSlugs(string $base, ?int $ignoreId = null): array
    {
        return [];
    }

    public function currentSlugForOld(string $oldSlug): ?string
    {
        return null;
    }

    public function assetPathsOf(array $ids): array
    {
        return [];
    }

    public function save(Project $project): void
    {
    }

    public function delete(int $id): void
    {
    }

    public function deleteMany(array $ids): void
    {
    }

    public function restore(int $id): void
    {
        $this->restoredIds[] = $id;
    }

    public function restoreMany(array $ids): void
    {
        $this->restoredManyIds = $ids;
    }

    public function forceDelete(int $id): void
    {
    }

    public function forceDeleteMany(array $ids): void
    {
    }

    public function setPublicationMany(array $ids, bool $published): void
    {
    }

    public function setFeatured(int $id, bool $featured): void
    {
    }

    public function reorderFeatured(array $ids): void
    {
    }

    public function setTechWriteup(int $id, ?int $articleId): void
    {
    }

    public function attachTag(int $projectId, int $skillId): void
    {
    }

    public function detachTag(int $projectId, int $skillId): void
    {
    }
}
