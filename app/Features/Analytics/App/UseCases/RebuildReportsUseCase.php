<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * The report rebuild (analytics.md:186-212, ADR-0017): deletes all
 * `analytics_reports` rows and rebuilds every span
 * (day/week/month/year/all — `all` = one whole-time row per
 * metric+dimension) from `analytics_daily_metrics` via the daily-metric
 * repository's SQL GROUP BY (`aggregateForSpan`). The value derivation
 * follows the catalog rules (analytics.md:172): weighted
 * `SUM(value)/SUM(samples)` for the avg-able metrics, plain MIN/MAX
 * for the `*_min`/`*_max` metrics, SUM otherwise.
 *
 * The full-rebuild mode (`rebuild(recomputeMetrics: true)` — the manual
 * `analytics:generate-reports` command and the dashboard "Generate
 * Now", 11.3.4) additionally recomputes the metrics first: deletes the
 * metric rows for the dates covered by the still-present events (the
 * ~90-day event window) and re-aggregates them from the oldest
 * existing event → today — plus the FULL feedback history
 * (`feedback_submissions` — feedback rows are never pruned, so the
 * metric can be re-derived even for days before the event window).
 * Metrics older than the event window are kept — a rebuild never loses
 * history it cannot re-derive. Called by `RebuildReportsJob` — the
 * daily path (after the metrics were just aggregated) and the
 * full-rebuild path (with the metric recompute).
 */
final class RebuildReportsUseCase
{
    public function __construct(
        private readonly AnalyticsDailyMetricRepositoryInterface $metrics,
        private readonly AnalyticsReportRepositoryInterface $reports,
        private readonly AnalyticsEventRepositoryInterface $events,
        private readonly AnalyticsReportDataReaderInterface $dataReader,
        private readonly AggregateDailyMetricsUseCase $aggregate,
    ) {
    }

    public function rebuild(bool $recomputeMetrics = false): void
    {
        if ($recomputeMetrics) {
            $this->recomputeMetricWindow();
        }

        $this->reports->replaceAll($this->reportRows());
    }

    /**
     * The full-rebuild metric recompute (analytics.md:209): the
     * deletion covers the event window only; the aggregation extends
     * back to the oldest feedback row (full feedback history).
     */
    private function recomputeMetricWindow(): void
    {
        $today = new DateTimeImmutable('today');
        $windowEnd = $today->modify('+1 day')->modify('-1 second');
        $aggregateFrom = null;

        $oldestEvent = $this->events->oldestEventDate();

        if ($oldestEvent !== null && $oldestEvent <= $today) {
            $this->metrics->deleteForDates($oldestEvent, $windowEnd);
            $aggregateFrom = $oldestEvent;
        }

        $oldestFeedback = $this->dataReader->oldestFeedbackDate();

        if ($oldestFeedback !== null
            && $oldestFeedback <= $today
            && ($aggregateFrom === null || $oldestFeedback < $aggregateFrom)
        ) {
            $aggregateFrom = $oldestFeedback;
        }

        if ($aggregateFrom !== null) {
            $this->aggregate->aggregate(new ReportPeriod($aggregateFrom, $windowEnd));
        }
    }

    /**
     * @return list<AnalyticsReport>
     */
    private function reportRows(): array
    {
        $createdAt = new DateTimeImmutable();
        $rows = [];

        foreach (MetricSpan::cases() as $span) {
            foreach ($this->metrics->aggregateForSpan($span) as $group) {
                $rows[] = $this->reportRow($group, $span, $createdAt);
            }
        }

        return $rows;
    }

    /**
     * @param array{
     *     metric: string,
     *     dimension: ?string,
     *     period_start: DateTimeImmutable,
     *     period_end: DateTimeImmutable,
     *     value_sum: float,
     *     samples_sum: int,
     *     value_min: float,
     *     value_max: float,
     * } $group
     */
    private function reportRow(array $group, MetricSpan $span, DateTimeImmutable $createdAt): AnalyticsReport
    {
        $metric = MetricName::from($group['metric']);

        return new AnalyticsReport(
            id: 0,
            metric: $metric,
            span: $span,
            dimension: $group['dimension'],
            periodStart: $group['period_start'],
            periodEnd: $group['period_end'],
            value: $this->spanValue($metric, $group),
            createdAt: $createdAt,
        );
    }

    /**
     * The per-metric-kind aggregation (analytics.md:172): weighted
     * average for the avg-able metrics, plain min/max for the
     * `*_min`/`*_max` metrics, plain sum otherwise.
     *
     * @param array{
     *     value_sum: float,
     *     samples_sum: int,
     *     value_min: float,
     *     value_max: float,
     * } $group
     */
    private function spanValue(MetricName $metric, array $group): float
    {
        return match (true) {
            $metric->isMinimum() => $group['value_min'],
            $metric->isMaximum() => $group['value_max'],
            $metric->isWeightedAverage() => $group['value_sum'] / $group['samples_sum'],
            default => $group['value_sum'],
        };
    }
}
