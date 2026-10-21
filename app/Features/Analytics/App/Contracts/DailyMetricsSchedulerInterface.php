<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\Contracts;

use DateTimeImmutable;

/**
 * Schedules the queued daily-metrics aggregation for a date range. An
 * abstraction over the infra job so the scheduler entry point never names
 * the queue class.
 */
interface DailyMetricsSchedulerInterface
{
    public function schedule(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): void;
}
