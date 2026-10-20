<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Services\DailyMetricsAggregator;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * The daily metrics entry point: delegates the catalog aggregation to
 * `DailyMetricsAggregator` and owns the default aggregation range — the
 * previous day plus the in-progress day, in the server-local timezone, so
 * the dashboard stays live. Shared by the scheduled command and the job.
 */
final class AggregateDailyMetricsUseCase
{
    public function __construct(
        private readonly DailyMetricsAggregator $aggregator,
    ) {
    }

    public function aggregate(ReportPeriod $period): void
    {
        $this->aggregator->aggregate($period);
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public function defaultDateRange(): array
    {
        $today = new DateTimeImmutable('today');

        return [
            $today->modify('-1 day'),
            $today->modify('+1 day')->modify('-1 second'),
        ];
    }
}
