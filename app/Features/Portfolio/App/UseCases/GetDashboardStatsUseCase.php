<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\UseCases;

use App\Features\Portfolio\App\Contracts\DashboardStatsReaderInterface;

/**
 * The admin dashboard stat cards. The reads live in the feature's Infra
 * layer (no cross-feature class imports).
 */
final class GetDashboardStatsUseCase
{
    public function __construct(
        private readonly DashboardStatsReaderInterface $stats,
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
