<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Services;

use App\Features\Analytics\App\Contracts\DailyMetricsSchedulerInterface;
use App\Features\Analytics\Infra\Jobs\AggregateDailyMetricsJob;
use DateTimeImmutable;
use Illuminate\Support\Facades\Bus;

/**
 * The queue-backed scheduler behind
 * {@see DailyMetricsSchedulerInterface}: dispatches the
 * `AggregateDailyMetricsJob`.
 */
final class LaravelDailyMetricsScheduler implements DailyMetricsSchedulerInterface
{
    public function schedule(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): void
    {
        Bus::dispatch(new AggregateDailyMetricsJob($dateFrom, $dateTo));
    }
}
