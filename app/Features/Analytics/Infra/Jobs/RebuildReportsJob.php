<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The report rebuild job (analytics.md:186-212, architecture.md:172):
 * deletes all `analytics_reports` rows and rebuilds every span
 * (day/week/month/year/all) from `analytics_daily_metrics` via
 * `RebuildReportsUseCase` — the full-rebuild mode additionally
 * recomputes the metrics for the still-present event window first.
 *
 * NOTE (step 11.3.2 seam): the implementation lands in step 11.3.3 —
 * this class currently carries the agreed constructor contract only
 * (`recomputeMetrics = false` for the daily path after metrics were
 * just aggregated; `true` for the dashboard "Generate Now" full
 * rebuild, 11.3.4). The `handle()` body is provided by 11.3.3.
 */
final class RebuildReportsJob implements ShouldQueue
{
    public function __construct(
        public readonly bool $recomputeMetrics = false,
    ) {
    }

    public function handle(): void
    {
        // Seam: 11.3.3 implements the report rebuild here.
    }
}
