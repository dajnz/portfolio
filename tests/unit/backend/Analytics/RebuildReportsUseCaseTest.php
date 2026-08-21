<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\UseCases\RebuildReportsUseCase;
use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeAnalyticsDailyMetricRepository;
use Tests\Support\Fakes\FakeAnalyticsReportRepository;

/**
 * The report rebuild (analytics.md:186-212, B290): every span
 * (day/week/month/year/all) rebuilt from the aggregated metric groups
 * — the per-metric-kind value derivation (weighted
 * `SUM(value)/SUM(samples)` for the avg-able metrics, plain MIN/MAX
 * for the `*_min`/`*_max` metrics, SUM otherwise), the `all` span as
 * one whole-time row per metric+dimension and the full `replaceAll` of
 * the stored reports. The group bucketing itself is the daily-metric
 * repository's SQL (integration-covered); here the use case maps the
 * groups to report rows.
 */

/**
 * A canned `aggregateForSpan` group row (the repository contract
 * shape: metric, dimension, period bounds + the raw group stats).
 *
 * @return array<string, mixed>
 */
function metricGroup(array $attributes = []): array
{
    return [
        'metric' => 'page_views',
        'dimension' => null,
        'period_start' => new DateTimeImmutable('2026-06-01'),
        'period_end' => new DateTimeImmutable('2026-08-21'),
        'value_sum' => 100.0,
        'samples_sum' => 100,
        'value_min' => 1.0,
        'value_max' => 10.0,
        ...$attributes,
    ];
}

/**
 * Runs the rebuild against the canned groups and returns the replaced
 * report rows.
 *
 * @return list<AnalyticsReport>
 */
function rebuiltReports(FakeAnalyticsDailyMetricRepository $metrics): array
{
    $reports = new FakeAnalyticsReportRepository();
    $useCase = new RebuildReportsUseCase($metrics, $reports);

    $useCase->rebuild();

    return $reports->replaced;
}

/**
 * @param list<AnalyticsReport> $rows
 *
 * @return list<AnalyticsReport>
 */
function reportsBySpan(array $rows, MetricSpan $span): array
{
    return array_values(array_filter(
        $rows,
        static fn (AnalyticsReport $row): bool => $row->toSpan() === $span,
    ));
}

/**
 * @param list<AnalyticsReport> $rows
 */
function reportByKey(array $rows, MetricSpan $span, string $metric, ?string $dimension = null): AnalyticsReport
{
    $matches = array_values(array_filter(
        $rows,
        static fn (AnalyticsReport $row): bool => $row->toSpan() === $span
            && $row->toMetric()->value === $metric
            && $row->toDimension() === $dimension,
    ));

    expect($matches)->toHaveCount(1);

    return $matches[0];
}

describe('RebuildReportsUseCase', function (): void {
    it('rebuilds every span from the aggregated metric groups', function (): void {
        $metrics = new FakeAnalyticsDailyMetricRepository();

        foreach (MetricSpan::cases() as $span) {
            $metrics->groups[$span->value] = [metricGroup()];
        }

        $rows = rebuiltReports($metrics);

        foreach (MetricSpan::cases() as $span) {
            expect(reportsBySpan($rows, $span))->toHaveCount(1);
        }
    });

    it('sums the count-like metrics across the group', function (): void {
        $metrics = new FakeAnalyticsDailyMetricRepository();
        $metrics->groups[MetricSpan::All->value] = [metricGroup([
            'metric' => 'page_views',
            'value_sum' => 250.0,
            'samples_sum' => 250,
            'value_min' => 1.0,
            'value_max' => 20.0,
        ])];

        $row = reportByKey(rebuiltReports($metrics), MetricSpan::All, 'page_views');

        expect($row->toValue())->toBe(250.0);
    });

    it('derives the weighted average for the avg-able metrics', function (): void {
        $metrics = new FakeAnalyticsDailyMetricRepository();
        $metrics->groups[MetricSpan::All->value] = [
            metricGroup(['metric' => 'time_on_page_seconds', 'value_sum' => 130.0, 'samples_sum' => 3]),
            metricGroup(['metric' => 'completion_rate_sum', 'value_sum' => 150.0, 'samples_sum' => 2]),
        ];

        $rows = rebuiltReports($metrics);

        expect(reportByKey($rows, MetricSpan::All, 'time_on_page_seconds')->toValue())->toBe(130.0 / 3.0)
            ->and(reportByKey($rows, MetricSpan::All, 'completion_rate_sum')->toValue())->toBe(150.0 / 2.0);
    });

    it('derives min and max for the `*_min`/`*_max` metrics', function (): void {
        $metrics = new FakeAnalyticsDailyMetricRepository();
        $metrics->groups[MetricSpan::All->value] = [
            metricGroup(['metric' => 'time_on_page_min', 'value_min' => 5.0, 'value_max' => 80.0]),
            metricGroup(['metric' => 'time_on_page_max', 'value_min' => 5.0, 'value_max' => 80.0]),
        ];

        $rows = rebuiltReports($metrics);

        expect(reportByKey($rows, MetricSpan::All, 'time_on_page_min')->toValue())->toBe(5.0)
            ->and(reportByKey($rows, MetricSpan::All, 'time_on_page_max')->toValue())->toBe(80.0);
    });

    it('carries the dimension and the period bounds onto the report rows', function (): void {
        $metrics = new FakeAnalyticsDailyMetricRepository();
        $metrics->groups[MetricSpan::All->value] = [metricGroup([
            'metric' => 'views',
            'dimension' => 'article:42',
            'period_start' => new DateTimeImmutable('2026-03-01'),
            'period_end' => new DateTimeImmutable('2026-08-21'),
        ])];

        $row = reportByKey(rebuiltReports($metrics), MetricSpan::All, 'views', 'article:42');

        expect($row->toDimension())->toBe('article:42')
            ->and($row->toPeriodStart()->format('Y-m-d'))->toBe('2026-03-01')
            ->and($row->toPeriodEnd()->format('Y-m-d'))->toBe('2026-08-21');
    });

    it('replaces the stored reports with the rebuilt rows', function (): void {
        $metrics = new FakeAnalyticsDailyMetricRepository();
        $metrics->groups[MetricSpan::Day->value] = [metricGroup()];

        $reports = new FakeAnalyticsReportRepository();
        $useCase = new RebuildReportsUseCase($metrics, $reports);

        $useCase->rebuild();

        expect($reports->replaceAllCalls)->toBe(1)
            ->and($reports->replaced)->toHaveCount(1)
            ->and($reports->replaced[0]->toCreatedAt())->toBeInstanceOf(DateTimeImmutable::class);
    });
});
