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
 * @param list<AnalyticsReport> $rows
 */
function seriesPoints(
    array $rows,
    MetricSpan $span,
    string $metric = 'page_views',
    ?string $dimension = null,
    string $now = '2026-08-21',
): array {
    $useCase = new GetMetricSeriesUseCase(new FakeAnalyticsReportRepository($rows));

    return $useCase->series(
        MetricName::from($metric),
        $span,
        $dimension,
        new DateTimeImmutable($now),
    )['points'];
}

/**
 * @param list<array{label: string, value: float}> $points
 *
 * @return list<string>
 */
function seriesLabels(array $points): array
{
    return array_map(static fn (array $point): string => $point['label'], $points);
}

it('returns the last 30 daily buckets chronologically, zero-filling the gaps', function (): void {
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Day, '2026-08-18', 10.0),
        seriesReportRow(MetricSpan::Day, '2026-08-20', 20.0),
    ], MetricSpan::Day);

    expect($points)->toHaveCount(30)
        ->and($points[0])->toBe(['label' => '2026-07-23', 'value' => 0.0])
        ->and($points[29])->toBe(['label' => '2026-08-21', 'value' => 0.0])
        ->and($points[25])->toBe(['label' => '2026-08-17', 'value' => 0.0])
        ->and($points[26])->toBe(['label' => '2026-08-18', 'value' => 10.0])
        ->and($points[27])->toBe(['label' => '2026-08-19', 'value' => 0.0])
        ->and($points[28])->toBe(['label' => '2026-08-20', 'value' => 20.0]);
});

it('returns the last 12 weekly buckets aligned to Mondays', function (): void {
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Week, '2026-06-01', 5.0),
        seriesReportRow(MetricSpan::Week, '2026-08-17', 30.0),
    ], MetricSpan::Week);

    expect(seriesLabels($points))->toBe([
        '2026-06-01',
        '2026-06-08',
        '2026-06-15',
        '2026-06-22',
        '2026-06-29',
        '2026-07-06',
        '2026-07-13',
        '2026-07-20',
        '2026-07-27',
        '2026-08-03',
        '2026-08-10',
        '2026-08-17',
    ])
        ->and($points[0]['value'])->toBe(5.0)
        ->and($points[11]['value'])->toBe(30.0);
});

it('returns the last 12 monthly buckets aligned to the first of the month', function (): void {
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Month, '2025-09-01', 10.0),
        seriesReportRow(MetricSpan::Month, '2026-08-01', 40.0),
    ], MetricSpan::Month);

    expect($points)->toHaveCount(12)
        ->and($points[0])->toBe(['label' => '2025-09-01', 'value' => 10.0])
        ->and($points[11])->toBe(['label' => '2026-08-01', 'value' => 40.0])
        ->and($points[1]['value'])->toBe(0.0);
});

it('filters the series to the given dimension — the per-target cta_clicks rows', function (): void {
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Week, '2026-08-17', 5.0, 'cta_clicks', 'github'),
        seriesReportRow(MetricSpan::Week, '2026-08-17', 3.0, 'cta_clicks', 'language'),
    ], MetricSpan::Week, 'cta_clicks', 'language');

    expect($points[11])->toBe(['label' => '2026-08-17', 'value' => 3.0]);
});

it('returns a dense zero window when nothing is stored', function (): void {
    $points = seriesPoints([], MetricSpan::Day);

    expect($points)->toHaveCount(30)
        ->and(array_sum(array_column($points, 'value')))->toBe(0.0);
});

it('folds the whole-time series into a daily cumulative for a short history', function (): void {
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Day, '2026-08-17', 10.0),
        seriesReportRow(MetricSpan::Day, '2026-08-19', 5.0),
        seriesReportRow(MetricSpan::Day, '2026-08-21', 20.0),
    ], MetricSpan::All);

    expect($points)->toBe([
        ['label' => '2026-08-17', 'value' => 10.0],
        ['label' => '2026-08-18', 'value' => 10.0],
        ['label' => '2026-08-19', 'value' => 15.0],
        ['label' => '2026-08-20', 'value' => 15.0],
        ['label' => '2026-08-21', 'value' => 35.0],
    ]);
});

it('buckets the whole-time series weekly once the daily points would exceed the limit', function (): void {
    // 2026-05-25 → 2026-08-21 = 89 daily buckets (> 30) but 13 weekly ones.
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Day, '2026-05-25', 4.0),
        seriesReportRow(MetricSpan::Day, '2026-08-21', 6.0),
    ], MetricSpan::All);

    expect($points[0])->toBe(['label' => '2026-05-25', 'value' => 4.0])
        ->and($points[12])->toBe(['label' => '2026-08-17', 'value' => 10.0])
        ->and(count($points))->toBeLessThanOrEqual(30)
        ->and(seriesLabels($points))->not->toContain('2026-05-26');
});

it('buckets the whole-time series monthly once the weekly points would exceed the limit', function (): void {
    // 2025-01-01 → 2026-08-21 = 20 monthly buckets.
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Day, '2025-01-01', 3.0),
        seriesReportRow(MetricSpan::Day, '2026-08-21', 7.0),
    ], MetricSpan::All);

    expect($points[0])->toBe(['label' => '2025-01-01', 'value' => 3.0])
        ->and($points[19])->toBe(['label' => '2026-08-01', 'value' => 10.0])
        ->and(seriesLabels($points))->not->toContain('2025-01-02');
});

it('buckets the whole-time series yearly once the monthly points would exceed the limit', function (): void {
    // 2020-01-01 → 2026-08-21 = 7 yearly buckets.
    $points = seriesPoints([
        seriesReportRow(MetricSpan::Day, '2020-01-01', 3.0),
        seriesReportRow(MetricSpan::Day, '2026-08-21', 7.0),
    ], MetricSpan::All);

    expect($points[0])->toBe(['label' => '2020-01-01', 'value' => 3.0])
        ->and($points[6])->toBe(['label' => '2026-01-01', 'value' => 10.0]);
});

it('returns an empty whole-time series when the metric has no day rows', function (): void {
    expect(seriesPoints([], MetricSpan::All))->toBe([]);
});

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
