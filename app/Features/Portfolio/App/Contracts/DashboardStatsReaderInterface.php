<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\Contracts;

/**
 * Reads the admin dashboard stat cards (view counts and content totals). The
 * cross-feature table reads live on the infra layer so the app layer never
 * queries other features' tables directly.
 */
interface DashboardStatsReaderInterface
{
    /**
     * @return array{
     *     total_views: int,
     *     blog_views: int,
     *     projects_count: int,
     *     articles_count: int,
     * }
     */
    public function read(): array;
}
