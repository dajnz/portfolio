<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\DTOs;

/**
 * The whole-time dashboard props (analytics.md:223-227, B293, 11.3.4)
 * for `GET /admin/analytics`: the per-tab metric blocks, the default
 * chart series and the default rankings. The controller maps it onto
 * the Inertia page props; the block keys are the stable identifiers
 * the frontend maps to i18n labels and to the series/rankings
 * parameters.
 */
final class AnalyticsDashboardDto
{
    /**
     * @param list<array{key: string, value: float, min?: float, max?: float}> $overviewBlocks
     * @param list<array{key: string, value: float, min?: float, max?: float}> $blogBlocks
     * @param list<array{key: string, value: float}> $projectsBlocks
     * @param array{metric: string, spans: array<string, array{points: list<array{label: string, value: float}>}>} $defaultChart
     * @param array{metric: string, scope: string, rows: array<string, list<array<string, mixed>>>} $defaultRankings
     */
    public function __construct(
        public readonly array $overviewBlocks,
        public readonly array $blogBlocks,
        public readonly array $projectsBlocks,
        public readonly array $defaultChart,
        public readonly array $defaultRankings,
    ) {
    }
}
