<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Jobs;

use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use App\Features\Analytics\App\UseCases\SendDailyDigestUseCase;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;

/**
 * The queued daily aggregation: aggregates `analytics_events` (+
 * `feedback` rows) into `analytics_daily_metrics` for the given date
 * range — defaulting to the previous day plus the in-progress day
 * (`AggregateDailyMetricsUseCase::defaultDateRange()`) — and then
 * dispatches the report rebuild (`RebuildReportsJob`) so the reports
 * never drift from the metrics. The previous day's digest mail
 * (`SendDailyDigestUseCase`) is queued after the rebuild — the digest
 * covers the range start's day. The date-range default and the email
 * decision live in the use cases, not here. Dispatched by
 * `analytics:generate-daily-metrics` (scheduled `0 2 * * *`).
 */
final class AggregateDailyMetricsJob implements ShouldQueue
{
    public function __construct(
        public readonly ?DateTimeImmutable $dateFrom = null,
        public readonly ?DateTimeImmutable $dateTo = null,
    ) {
    }

    public function handle(
        AggregateDailyMetricsUseCase $aggregate,
        SendDailyDigestUseCase $digestMail,
    ): void {
        [$dateFrom, $dateTo] = $this->resolveDateRange($aggregate);

        $aggregate->aggregate(new ReportPeriod($dateFrom, $dateTo));

        // The report rebuild runs after every aggregation — reports
        // are fully rebuilt from the daily metrics, so they cannot
        // drift.
        Bus::dispatch(new RebuildReportsJob());

        $digestMail->sendForDay($dateFrom);
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function resolveDateRange(AggregateDailyMetricsUseCase $aggregate): array
    {
        if ($this->dateFrom !== null && $this->dateTo !== null) {
            return [$this->dateFrom, $this->dateTo];
        }

        return $aggregate->defaultDateRange();
    }
}
