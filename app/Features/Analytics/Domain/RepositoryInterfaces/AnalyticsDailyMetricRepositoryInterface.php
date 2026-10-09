<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\RepositoryInterfaces;

use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use DateTimeImmutable;

/**
 * Persistence for `analytics_daily_metrics` (database.md:513-529,
 * ADR-0017): the long-term per-day aggregate store, written by the daily
 * aggregation (11.3.2) and read by the report rebuild (11.3.3) and
 * the email digest (11.3.5). Rows are upserted per
 * `(metric, date, dimension)` — the storage is append-only-ish and
 * never pruned.
 */
interface AnalyticsDailyMetricRepositoryInterface
{
    /**
     * Upserts the given metrics on the `(metric, date, dimension)`
     * unique key — re-aggregating a day overwrites `value` + `samples`.
     *
     * @param list<AnalyticsMetric> $metrics
     */
    public function upsertBatch(array $metrics): void;

    /**
     * The stored metrics with a date within the inclusive bounds,
     * optionally filtered by metric and/or exact dimension (null =
     * no filter). Ordered by date ascending, then metric, then
     * dimension.
     *
     * @return list<AnalyticsMetric>
     */
    public function rowsBetween(
        ?MetricName $metric,
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        ?string $dimension = null,
    ): array;

    /**
     * Deletes the rows whose date falls within the inclusive bounds
     * (the full-rebuild metric-window recompute, analytics.md:209).
     * Returns the number of deleted rows.
     */
    public function deleteForDates(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): int;

    /** Deletes every stored metric row (the `analytics:wipe` reset). */
    public function deleteAll(): int;

    /**
     * The distinct dates that have at least one stored row, ordered
     * ascending — the coverage signal for the report rebuild and the
     * digest.
     *
     * @return list<DateTimeImmutable>
     */
    public function datesCovered(): array;

    /**
     * The metric rows grouped for one report span (the report-rebuild
     * source, 11.3.3): one group per `(metric, dimension, period
     * bucket)` with the raw group stats — the caller derives the
     * per-metric-kind report value (weighted `SUM(value)/SUM(samples)`
     * for the avg-able metrics, the plain min/max for the `*_min`/
     * `*_max` metrics, the plain sum otherwise — analytics.md:172).
     *
     * Bucketing is calendar-based (analytics.md:202): `day` = the
     * calendar date, `week` = Monday–Sunday, `month`/`year` = the
     * calendar bounds, `all` = one group per metric+dimension spanning
     * the stored history. Group row shape: `{metric: string,
     * dimension: ?string, period_start: DateTimeImmutable,
     * period_end: DateTimeImmutable, value_sum: float, samples_sum:
     * int, value_min: float, value_max: float}` — ordered by metric,
     * dimension, period start.
     *
     * @return list<array{
     *     metric: string,
     *     dimension: ?string,
     *     period_start: DateTimeImmutable,
     *     period_end: DateTimeImmutable,
     *     value_sum: float,
     *     samples_sum: int,
     *     value_min: float,
     *     value_max: float,
     * }>
     */
    public function aggregateForSpan(MetricSpan $span): array;
}
