<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Jobs;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use App\Features\Analytics\App\UseCases\RebuildReportsUseCase;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The report rebuild job (analytics.md:186-212, architecture.md:172):
 * deletes all `analytics_reports` rows and rebuilds every span
 * (day/week/month/year/all) from `analytics_daily_metrics` via
 * `RebuildReportsUseCase`. The full-rebuild mode (`recomputeMetrics` —
 * the manual `analytics:generate-reports` command and the dashboard
 * "Generate Now", 11.3.4) additionally recomputes the metrics first:
 * deletes the metric rows for the dates covered by the still-present
 * events (the ~90-day event window) and re-aggregates them from the
 * oldest existing event → today — plus the FULL feedback history
 * (`feedback_submissions` — feedback rows are never pruned, so the
 * metric can be re-derived even for days before the event window).
 * Metrics older than the event window are kept — a rebuild never loses
 * history it cannot re-derive.
 */
final class RebuildReportsJob implements ShouldQueue
{
    public function __construct(
        public readonly bool $recomputeMetrics = false,
    ) {
    }

    public function handle(
        RebuildReportsUseCase $rebuild,
        AggregateDailyMetricsUseCase $aggregate,
        AnalyticsDailyMetricRepositoryInterface $metrics,
        AnalyticsEventRepositoryInterface $events,
        AnalyticsReportDataReaderInterface $dataReader,
    ): void {
        if ($this->recomputeMetrics) {
            $this->recomputeMetricWindow($aggregate, $metrics, $events, $dataReader);
        }

        $rebuild->rebuild();
    }

    /**
     * The full-rebuild metric recompute (analytics.md:209): the
     * deletion covers the event window only; the aggregation extends
     * back to the oldest feedback row (full feedback history).
     */
    private function recomputeMetricWindow(
        AggregateDailyMetricsUseCase $aggregate,
        AnalyticsDailyMetricRepositoryInterface $metrics,
        AnalyticsEventRepositoryInterface $events,
        AnalyticsReportDataReaderInterface $dataReader,
    ): void {
        $today = new DateTimeImmutable('today');
        $windowEnd = $today->modify('+1 day')->modify('-1 second');
        $aggregateFrom = null;

        $oldestEvent = $events->oldestEventDate();

        if ($oldestEvent !== null && $oldestEvent <= $today) {
            $metrics->deleteForDates($oldestEvent, $windowEnd);
            $aggregateFrom = $oldestEvent;
        }

        $oldestFeedback = $dataReader->oldestFeedbackDate();

        if ($oldestFeedback !== null
            && $oldestFeedback <= $today
            && ($aggregateFrom === null || $oldestFeedback < $aggregateFrom)
        ) {
            $aggregateFrom = $oldestFeedback;
        }

        if ($aggregateFrom !== null) {
            $aggregate->aggregate(new ReportPeriod($aggregateFrom, $windowEnd));
        }
    }
}
