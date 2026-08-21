<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\UseCases\GetMetricSeriesUseCase;
use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeAnalyticsReportRepository;

/**
 * The metric chart series (analytics.md:225, 11.3.4): the span points
 * (period_start labels, chronological) via `GET /admin/analytics/
 * series?metric=&span=`, the cumulative running total for `span=all`,
 * the per-target `cta_clicks` dimension filter, and the 90-point cap.
 */

/**
 * @param list<AnalyticsReport> $rows
 */
function seriesPoints(array $rows, MetricSpan $span, string $metric = 'page_views', ?string $dimension = null): array
{
    $useCase = new GetMetricSeriesUseCase(new FakeAnalyticsReportRepository($rows));

    return $useCase->series(MetricName::from($metric), $span, $dimension)['points'];
}

it('returns the span points chronologically with ISO date labels', function (): void {
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Day, '2026-08-20', 20.0),
        seriesReportRow(MetricSpan::Day, '2026-08-19', 10.0),
    ], MetricSpan::Day);

    expect($points)->toBe([
        ['label' => '2026-08-19', 'value' => 10.0],
        ['label' => '2026-08-20', 'value' => 20.0],
    ]);
});

it('returns the cumulative running total for the whole-time span', function (): void {
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Day, '2026-08-19', 10.0),
        seriesReportRow(MetricSpan::Day, '2026-08-20', 20.0),
        seriesReportRow(MetricSpan::Day, '2026-08-21', 30.0),
    ], MetricSpan::All);

    expect($points)->toBe([
        ['label' => '2026-08-19', 'value' => 10.0],
        ['label' => '2026-08-20', 'value' => 30.0],
        ['label' => '2026-08-21', 'value' => 60.0],
    ]);
});

it('filters the series to the given dimension — the per-target cta_clicks rows', function (): void {
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Week, '2026-08-17', 5.0, 'cta_clicks', 'github'),
        seriesReportRow(MetricSpan::Week, '2026-08-17', 3.0, 'cta_clicks', 'language'),
    ], MetricSpan::Week, 'cta_clicks', 'language');

    expect($points)->toBe([['label' => '2026-08-17', 'value' => 3.0]]);
});

it('caps the span points at 90', function (): void {
    $rows = array_map(
        static fn (int $day): AnalyticsReport => seriesReportRow(
            MetricSpan::Day,
            '2026-06-'.sprintf('%02d', ($day - 1) % 30 + 1),
            (float) $day,
        ),
        range(1, 91),
    );

    $points = seriesPoints($rows, MetricSpan::Day);

    expect($points)->toHaveCount(90)
        ->and($points[0]['label'])->toBe('2026-06-01')
        ->and($points[89]['label'])->toBe('2026-06-30');
});

/**
 * Builds a series row of the given metric at the span — dimension
 * defaults to null (the global rows).
 */
function seriesReportRow(
    MetricSpan $span,
    string $periodStart,
    float $value,
    string $metric = 'page_views',
    ?string $dimension = null,
): AnalyticsReport {
    return new AnalyticsReport(
        id: 0,
        metric: MetricName::from($metric),
        span: $span,
        dimension: $dimension,
        periodStart: new DateTimeImmutable($periodStart),
        periodEnd: new DateTimeImmutable($periodStart),
        value: $value,
        createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
    );
}
