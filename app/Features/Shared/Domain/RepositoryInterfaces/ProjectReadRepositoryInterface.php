<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

use App\Features\Shared\Domain\Entities\FeaturedProject;

/**
 * Read-only access to projects for cross-feature consumers (ADR-0003): the
 * landing page reads featured projects and the published-project count
 * via this interface — the write side and the full read side belong to
 * the Projects feature (7.1).
 */
interface ProjectReadRepositoryInterface
{
    /**
     * Published + featured projects ordered by `featured_sort_order`
     * (content-management.md:116-120), with locale-resolved titles and
     * tech tags.
     *
     * @return FeaturedProject[]
     */
    public function findFeatured(string $locale, int $limit = 5): array;

    /**
     * Count of published, non-trashed projects — the "View all projects"
     * link on the landing page renders only when
     * `countPublished() > count(findFeatured())` (content-management.md:116-120).
     */
    public function countPublished(): int;
}
