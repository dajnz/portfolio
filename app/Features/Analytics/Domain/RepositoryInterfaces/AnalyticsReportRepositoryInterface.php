<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\RepositoryInterfaces;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;

/**
 * Persistence for `analytics_reports` (database.md:531-548, B290):
 * per-metric aggregates over the daily metrics, pre-computed for every
 * supported span and fully rebuilt on every metrics generation — the
 * read shapes power the dashboard: whole-time blocks
 * (`span = 'all'`, dimension NULL), chart series (the last-N
 * dimension-NULL rows of a span) and rankings (`span = 'all'`,
 * dimension rows, ordered by value).
 */
interface AnalyticsReportRepositoryInterface
{
    /**
     * Replaces the whole table with the given reports (the full
     * rebuild: every existing row is deleted first, then the batch is
     * stored — analytics.md:208).
     *
     * @param list<AnalyticsReport> $reports
     */
    public function replaceAll(array $reports): void;

    /**
     * Upserts the given reports on the
     * `(metric, span, dimension, period_start, period_end)` unique key
     * — re-aggregating a period overwrites `value`.
     *
     * @param list<AnalyticsReport> $reports
     */
    public function upsertBatch(array $reports): void;

    /**
     * The whole-time blocks for the given metrics
     * (`span = 'all'`, dimension NULL rows), ordered by metric name
     * ascending — the caller orders them by its tab layout.
     *
     * @param list<MetricName> $metricNames
     *
     * @return list<AnalyticsReport>
     */
    public function blocks(array $metricNames): array;

    /**
     * The last `$limit` dimension-NULL rows for the metric at the
     * span, newest period first (chart series — the caller reverses
     * them for chronological display).
     *
     * @return list<AnalyticsReport>
     */
    public function series(MetricName $metric, MetricSpan $span, int $limit): array;

    /**
     * The `span = 'all'` dimension rows for the metric ordered by
     * value — `top` descending, `bottom` ascending (rankings).
     *
     * @return list<AnalyticsReport>
     */
    public function rankings(MetricName $metric, string $direction, int $limit): array;

    /**
     * The distinct spans that have at least one row for the metric,
     * in the span enumeration order (day, week, month, year, all).
     *
     * @return list<MetricSpan>
     */
    public function metricSpans(MetricName $metric): array;

    /**
     * The cumulative running total of the metric's day rows — the
     * "whole time" chart source (analytics.md:199, B292): every
     * dimension-NULL `span = 'day'` row in chronological order, each
     * carrying the running total of its value (a pure fold over the
     * day rows — no window functions).
     *
     * @return list<AnalyticsReport>
     */
    public function cumulativeSeries(MetricName $metric): array;
}
