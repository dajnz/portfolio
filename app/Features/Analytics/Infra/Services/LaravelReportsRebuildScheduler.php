<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Services;

use App\Features\Analytics\App\Contracts\ReportsRebuildSchedulerInterface;
use App\Features\Analytics\Infra\Jobs\RebuildReportsJob;
use Illuminate\Support\Facades\Bus;

/**
 * The queue-backed scheduler behind the
 * {@see ReportsRebuildSchedulerInterface}: dispatches the
 * `RebuildReportsJob`.
 */
final class LaravelReportsRebuildScheduler implements ReportsRebuildSchedulerInterface
{
    public function schedule(bool $recomputeMetrics): void
    {
        Bus::dispatch(new RebuildReportsJob(recomputeMetrics: $recomputeMetrics));
    }
}
