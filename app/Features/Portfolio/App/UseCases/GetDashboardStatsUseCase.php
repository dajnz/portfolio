<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\Infra\Services\DashboardStatsReader;

/**
 * The admin dashboard stat cards (admin-section-ui.md:224-228, ADR-0017):
 * count cards exclude trashed; "Total Views" sums `article_views.views`
 * across ALL articles incl. unpublished + trashed; "Blog Views" counts
 * published, non-trashed articles only. The reads live in the feature's
 * Infra layer (ADR-0003 — no cross-feature class imports).
 */
final class GetDashboardStatsUseCase
{
    public function __construct(
        private readonly DashboardStatsReader $stats,
    ) {
    }

    /**
     * @return array{
     *     total_views: int,
     *     blog_views: int,
     *     projects_count: int,
     *     articles_count: int,
     * }
     */
    public function get(): array
    {
        return $this->stats->read();
    }
}
