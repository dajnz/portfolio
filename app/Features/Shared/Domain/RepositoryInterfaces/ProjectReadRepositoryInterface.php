<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

use App\Features\Shared\Domain\Entities\FeaturedProject;

/**
 * Read-only access to projects for cross-feature consumers (the landing
 * page's featured projects and published-project count).
 */
interface ProjectReadRepositoryInterface
{
    /**
     * Published + featured projects ordered by `featured_sort_order`, with
     * locale-resolved titles and tech tags.
     *
     * @return FeaturedProject[]
     */
    public function findFeatured(string $locale, int $limit = 5): array;

    /**
     * Count of published, non-trashed projects — the landing page's
     * "View all projects" link renders only when
     * `countPublished() > count(findFeatured())`.
     */
    public function countPublished(): int;
}
