<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Contracts\ReportsRebuildSchedulerInterface;

/**
 * The dashboard's "Generate Now": schedules the full report rebuild
 * (metrics recomputed first) through the
 * {@see ReportsRebuildSchedulerInterface} — the request returns
 * immediately (202) and the page reloads on completion.
 */
final class GenerateAnalyticsReportsUseCase
{
    public function __construct(
        private readonly ReportsRebuildSchedulerInterface $scheduler,
    ) {
    }

    public function generate(): void
    {
        $this->scheduler->schedule(recomputeMetrics: true);
    }
}
