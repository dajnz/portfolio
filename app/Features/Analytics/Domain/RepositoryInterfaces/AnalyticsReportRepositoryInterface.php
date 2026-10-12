<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\RepositoryInterfaces;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use DateTimeImmutable;

/**
 * Persistence for `analytics_reports`: per-metric aggregates over the
 * daily metrics, pre-computed for every supported span and fully
 * rebuilt on every metrics generation — the read shapes power the
 * dashboard: whole-time blocks (`span = 'all'`, dimension NULL), chart
 * series (the dimension-NULL rows of a span from a given date) and
 * rankings (`span = 'all'`, dimension rows, ordered by value).
 */
interface AnalyticsReportRepositoryInterface
{
    /**
     * Replaces the whole table with the given reports (the full
     * rebuild: every existing row is deleted first, then the batch is
     * stored).
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
     * The rows of the metric at the span with `period_start` on or
     * after `$from`, chronological (chart series — the caller fills the
     * missing window buckets). A null `$dimension` reads the
     * dimension-NULL rows (the global series); a given dimension filters
     * to that exact dimension — the per-target `cta_clicks` series.
     *
     * @return list<AnalyticsReport>
     */
    public function series(MetricName $metric, MetricSpan $span, DateTimeImmutable $from, ?string $dimension = null): array;

    /**
     * The `span = 'all'` dimension rows for the metric ordered by
     * value — `top` descending, `bottom` ascending (rankings). A
     * `$dimensionPrefix` filters to one dimension family
     * (`article:`/`project:` — the `views`/`unique_visitors` metrics
     * span both); null returns every dimension row.
     *
     * @return list<AnalyticsReport>
     */
    public function rankings(MetricName $metric, string $direction, int $limit, ?string $dimensionPrefix = null): array;

    /**
     * The whole-time (`span = 'all'`) dimension rows of the metric,
     * dimension ascending — the block-total source: the per-target
     * `cta_clicks` rows sum to the CTA total and carry the
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
     * Every `span = 'day'` row of the metric, chronological — the raw
     * day values the whole-time chart buckets and folds into its running
     * total. A null `$dimension` reads the dimension-NULL rows (the
     * global series); a given dimension filters to that exact dimension
     * — the per-target `cta_clicks` dynamics.
     *
     * @return list<AnalyticsReport>
     */
    public function daySeries(MetricName $metric, ?string $dimension = null): array;

    /**
     * The oldest `span = 'day'` `period_start` of the metric across all
     * dimensions, or null when the metric has no day rows. Anchors the
     * whole-time chart's adaptive bucketing — the metric-wide history
     * length, so every dimension of a multi-series chart resolves to the
     * same bucket size.
     */
    public function oldestDayStart(MetricName $metric): ?DateTimeImmutable;
}
