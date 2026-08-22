<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Jobs;

use App\Features\Analytics\App\UseCases\RebuildReportsUseCase;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The report rebuild job (analytics.md:186-212, architecture.md:172):
 * a thin queued shell over `RebuildReportsUseCase::rebuild()` — the
 * daily path (`recomputeMetrics: false`, the metrics were just
 * aggregated) rebuilds the reports only; the full-rebuild path
 * (`recomputeMetrics: true` — the manual `analytics:generate-reports`
 * command and the dashboard "Generate Now", 11.3.4) additionally
 * recomputes the event-window metrics first. The whole metric/report
 * management lives in the use case, not the job.
 */
final class RebuildReportsJob implements ShouldQueue
{
    public function __construct(
        public readonly bool $recomputeMetrics = false,
    ) {
    }

    public function handle(RebuildReportsUseCase $rebuild): void
    {
        $rebuild->rebuild($this->recomputeMetrics);
    }
}
