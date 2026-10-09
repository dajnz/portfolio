<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\RepositoryInterfaces;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;

/**
 * Persistence for `analytics_reports` (database.md:531-548, ADR-0017):
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

    /** Deletes every stored report row (the `analytics:wipe` reset). */
    public function deleteAll(): int;

    /**
     * Upserts the given reports on the
     * `(metric, span, dimension, period_start, period_end)` unique key
     * — re-aggregating a period overwrites `value`.
     *
     * @param list<AnalyticsReport> $reports
     */
    public function upsertBatch(array $reports): void;

    /**
     * The whole-time scalar values for the given metrics
     * (`span = 'all'`, dimension NULL rows) — the dashboard block
     * source, ordered by metric name ascending (the caller orders them
     * by its tab layout).
     *
     * @param list<MetricName> $metricNames
     *
     * @return list<AnalyticsReport>
     */
    public function wholeTimeScalarValues(array $metricNames): array;

    /**
     * The last `$limit` rows for the metric at the span, newest period
     * first (chart series — the caller reverses them for chronological
     * display). A null `$dimension` reads the dimension-NULL rows (the
     * global series); a given dimension filters to that exact
     * dimension — the per-target `cta_clicks` series (11.3.4).
     *
     * @return list<AnalyticsReport>
     */
    public function series(MetricName $metric, MetricSpan $span, int $limit, ?string $dimension = null): array;

    /**
     * The `span = 'all'` dimension rows for the metric ordered by
     * value — `top` descending, `bottom` ascending (rankings). A
     * `$dimensionPrefix` filters to one dimension family
     * (`article:`/`project:` — the `views`/`unique_visitors` metrics
     * span both, 11.3.4); null returns every dimension row.
     *
     * @return list<AnalyticsReport>
     */
    public function rankings(MetricName $metric, string $direction, int $limit, ?string $dimensionPrefix = null): array;

    /**
     * The whole-time (`span = 'all'`) dimension rows of the metric,
     * dimension ascending — the block-total source (11.3.4): the
     * per-target `cta_clicks` rows sum to the CTA total and carry the
     * language-switch block, the `views`/`unique_visitors` project
     * rows sum to the projects blocks, and the `*_min`/`*_max` rows
     * join the time/read/completion rankings by dimension.
     *
     * @return list<AnalyticsReport>
     */
    public function dimensionRows(MetricName $metric, ?string $dimensionPrefix = null): array;

    /**
     * The distinct spans that have at least one row for the metric,
     * in the span enumeration order (day, week, month, year, all).
     *
     * @return list<MetricSpan>
     */
    public function metricSpans(MetricName $metric): array;

    /**
     * The cumulative running total of the metric's day rows — the
     * "whole time" chart source (analytics.md:199, ADR-0017): every
     * `span = 'day'` row in chronological order, each carrying the
     * running total of its value (a pure fold over the day rows — no
     * window functions). A null `$dimension` folds the dimension-NULL
     * rows (the global series); a given dimension folds that exact
     * dimension's day rows — the per-target `cta_clicks` cumulative
     * (11.3.4).
     *
     * @return list<AnalyticsReport>
     */
    public function cumulativeSeries(MetricName $metric, ?string $dimension = null): array;
}
