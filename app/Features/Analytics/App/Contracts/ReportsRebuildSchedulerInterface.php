<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\Contracts;

/**
 * Schedules the async analytics report rebuild the dashboard's "Generate
 * Now" triggers. An abstraction over the infra `RebuildReportsJob` so
 * the app-layer use cases never name the queue class.
 */
interface ReportsRebuildSchedulerInterface
{
    public function schedule(bool $recomputeMetrics): void;
}
