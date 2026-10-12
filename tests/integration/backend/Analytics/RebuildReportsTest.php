<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\App\UseCases\RebuildReportsUseCase;
use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Infra\Jobs\RebuildReportsJob;
use App\Features\Analytics\Infra\Models\AnalyticsEventModel;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use DateTimeImmutable;

/**
 * The report rebuild on real PostgreSQL: `rebuild()` produces the full
 * span matrix from the stored daily metrics (calendar bucketing incl.
 * the week/year boundaries, the weighted aggregation, the whole-time
 * `all` rows); the full-rebuild path deletes the metric rows for the
 * still-present event window, re-aggregates them from the oldest event →
 * today (plus the FULL feedback history — feedback rows are never
 * pruned), keeps the metrics older than the event window, and rebuilds
 * the reports.
 */

function rebuildMetric(
    string $date,
    float $value,
    MetricName $metric = MetricName::PageViews,
    ?string $dimension = null,
    int $samples = 1,
): AnalyticsMetric {
    return new AnalyticsMetric(
        id: 0,
        metric: $metric,
        date: new DateTimeImmutable($date),
        dimension: $dimension,
        value: $value,
        samples: $samples,
        createdAt: new DateTimeImmutable($date.' 02:00:00'),
    );
}

function runFullRebuildJob(): void
{
    $job = new RebuildReportsJob(recomputeMetrics: true);
    $job->handle(app(RebuildReportsUseCase::class));
}

/**
 * The shared rebuild fixture: `page_views` across the week boundary
 * (2026-06-29 Mon → 2026-07-05 Sun) plus the avg-able/min-max and the
 * dimension rows.
 */
function seedRebuildFixture(): void
{
    app(AnalyticsDailyMetricRepositoryInterface::class)->upsertBatch([
        rebuildMetric('2026-06-29', 1.0),
        rebuildMetric('2026-07-01', 2.0),
        rebuildMetric('2026-07-05', 3.0),
        rebuildMetric('2026-07-06', 4.0),
        // The avg-able + min/max pairs.
        rebuildMetric('2026-07-09', 10.0, MetricName::TimeOnPageSeconds, samples: 1),
        rebuildMetric('2026-07-10', 120.0, MetricName::TimeOnPageSeconds, samples: 2),
        rebuildMetric('2026-07-09', 5.0, MetricName::TimeOnPageMin),
        rebuildMetric('2026-07-10', 8.0, MetricName::TimeOnPageMax),
        // The dimension rows (rankings).
        rebuildMetric('2026-07-09', 3.0, MetricName::Views, 'article:42'),
        rebuildMetric('2026-07-09', 5.0, MetricName::CtaClicks, 'github'),
    ]);
}

/**
 * The shared full-rebuild fixture: pre-window metrics (older than any
 * event — must survive), a stale in-window metric row (must be
 * overwritten), two in-window page-view events and feedback rows
 * before and inside the event window.
 */
function seedFullRebuildFixture(): void
{
    app(AnalyticsDailyMetricRepositoryInterface::class)->upsertBatch([
        rebuildMetric('2026-06-01', 5.0),
        rebuildMetric('2026-06-02', 7.0),
        rebuildMetric('2026-07-09', 999.0),
    ]);

    AnalyticsEventModel::factory()->create([
        'event_type' => 'page_view',
        'ip_address' => 'ip-a',
        'page_type' => 'landing',
        'created_at' => '2026-07-09 10:00:00',
    ]);
    AnalyticsEventModel::factory()->create([
        'event_type' => 'page_view',
        'ip_address' => 'ip-b',
        'page_type' => 'landing',
        'created_at' => '2026-07-09 10:00:01',
    ]);

    FeedbackModel::factory()->create(['created_at' => '2026-06-05 11:00:00']);
    FeedbackModel::factory()->create(['created_at' => '2026-07-09 11:00:00']);
}

describe('RebuildReportsUseCase (integration)', function (): void {
    it('builds the day-span series from the stored metrics', function (): void {
        seedRebuildFixture();
        app(RebuildReportsUseCase::class)->rebuild();

        // The day rows (the series read is chronological).
        $daySeries = app(AnalyticsReportRepositoryInterface::class)
            ->series(MetricName::PageViews, MetricSpan::Day, new DateTimeImmutable('2026-06-01'));

        expect($daySeries)->toHaveCount(4)
            ->and(array_map(
                static fn ($r): string => $r->toPeriodStart()->format('Y-m-d'),
                $daySeries,
            ))->toBe(['2026-06-29', '2026-07-01', '2026-07-05', '2026-07-06']);
    });

    it('buckets the week span across the Monday boundary', function (): void {
        seedRebuildFixture();
        app(RebuildReportsUseCase::class)->rebuild();

        // The week rows span their Monday–Sunday calendar bounds.
        $weekSeries = app(AnalyticsReportRepositoryInterface::class)
            ->series(MetricName::PageViews, MetricSpan::Week, new DateTimeImmutable('2026-06-01'));

        expect($weekSeries)->toHaveCount(2)
            ->and(array_map(
                static fn ($r): string => $r->toPeriodStart()->format('Y-m-d'),
                $weekSeries,
            ))->toBe(['2026-06-29', '2026-07-06'])
            ->and($weekSeries[0]->toPeriodEnd()->format('Y-m-d'))->toBe('2026-07-05')
            ->and($weekSeries[1]->toPeriodEnd()->format('Y-m-d'))->toBe('2026-07-12');
    });

    it('sums the whole-time block over the stored history', function (): void {
        seedRebuildFixture();
        app(RebuildReportsUseCase::class)->rebuild();

        $blocks = app(AnalyticsReportRepositoryInterface::class)
            ->wholeTimeScalarValues([MetricName::PageViews]);

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]->toValue())->toBe(10.0)
            ->and($blocks[0]->toPeriodStart()->format('Y-m-d'))->toBe('2026-06-29')
            ->and($blocks[0]->toPeriodEnd()->format('Y-m-d'))->toBe('2026-07-06');
    });

    it('derives the weighted average for the avg-able metrics', function (): void {
        seedRebuildFixture();
        app(RebuildReportsUseCase::class)->rebuild();

        $reports = app(AnalyticsReportRepositoryInterface::class);

        // Per-day rows (120/2 = 60 on the 10th), chronological, and the
        // whole-time block (130/3).
        $avgDay = $reports->series(MetricName::TimeOnPageSeconds, MetricSpan::Day, new DateTimeImmutable('2026-07-01'));

        expect(array_map(static fn ($r): float => $r->toValue(), $avgDay))->toBe([10.0, 60.0]);

        $avgBlock = $reports->wholeTimeScalarValues([MetricName::TimeOnPageSeconds]);

        expect($avgBlock)->toHaveCount(1)
            ->and($avgBlock[0]->toValue())->toEqualWithDelta(130.0 / 3.0, 1.0e-9);
    });

    it('derives the whole-time min and max rows', function (): void {
        seedRebuildFixture();
        app(RebuildReportsUseCase::class)->rebuild();

        $reports = app(AnalyticsReportRepositoryInterface::class);
        $minBlock = $reports->wholeTimeScalarValues([MetricName::TimeOnPageMin]);
        $maxBlock = $reports->wholeTimeScalarValues([MetricName::TimeOnPageMax]);

        expect($minBlock)->toHaveCount(1)
            ->and($minBlock[0]->toValue())->toBe(5.0)
            ->and($maxBlock)->toHaveCount(1)
            ->and($maxBlock[0]->toValue())->toBe(8.0);
    });

    it('builds the whole-time dimension rows for the rankings', function (): void {
        seedRebuildFixture();
        app(RebuildReportsUseCase::class)->rebuild();

        $rankings = app(AnalyticsReportRepositoryInterface::class)
            ->rankings(MetricName::Views, 'top', 10);

        expect($rankings)->toHaveCount(1)
            ->and($rankings[0]->toDimension())->toBe('article:42')
            ->and($rankings[0]->toValue())->toBe(3.0);
    });

    it('buckets the year span across the new-year boundary', function (): void {
        app(AnalyticsDailyMetricRepositoryInterface::class)->upsertBatch([
            rebuildMetric('2026-12-31', 3.0),
            rebuildMetric('2027-01-01', 4.0),
        ]);

        app(RebuildReportsUseCase::class)->rebuild();

        $yearSeries = app(AnalyticsReportRepositoryInterface::class)
            ->series(MetricName::PageViews, MetricSpan::Year, new DateTimeImmutable('2026-01-01'));

        expect($yearSeries)->toHaveCount(2)
            ->and(array_map(
                static fn ($r): string => $r->toPeriodStart()->format('Y-m-d'),
                $yearSeries,
            ))->toBe(['2026-01-01', '2027-01-01'])
            ->and($yearSeries[0]->toPeriodEnd()->format('Y-m-d'))->toBe('2026-12-31')
            ->and($yearSeries[1]->toPeriodEnd()->format('Y-m-d'))->toBe('2027-12-31');
    });

    it('rebuilds into an empty report set when no metrics are stored', function (): void {
        app(RebuildReportsUseCase::class)->rebuild();

        expect(app(AnalyticsReportRepositoryInterface::class)->metricSpans(MetricName::PageViews))->toBe([]);
    });
});

describe('RebuildReportsJob full rebuild (integration)', function (): void {
    it('keeps the metrics older than the event window', function (): void {
        seedFullRebuildFixture();
        runFullRebuildJob();

        $kept = app(AnalyticsDailyMetricRepositoryInterface::class)->rowsBetween(
            metric: MetricName::PageViews,
            dateFrom: new DateTimeImmutable('2026-06-01'),
            dateTo: new DateTimeImmutable('2026-06-02'),
        );

        expect(array_map(static fn ($row): float => $row->toValue(), $kept))->toBe([5.0, 7.0]);
    });

    it('recomputes the in-window metrics from the events', function (): void {
        seedFullRebuildFixture();
        runFullRebuildJob();

        // The window's metrics were recomputed (the stale 999 is gone).
        $window = app(AnalyticsDailyMetricRepositoryInterface::class)->rowsBetween(
            metric: MetricName::PageViews,
            dateFrom: new DateTimeImmutable('2026-07-09'),
            dateTo: new DateTimeImmutable('2026-07-09'),
        );

        expect($window)->toHaveCount(1)
            ->and($window[0]->toValue())->toBe(2.0);
    });

    it('re-derives the full feedback history (pre-window days included)', function (): void {
        seedFullRebuildFixture();
        runFullRebuildJob();

        $feedback = app(AnalyticsDailyMetricRepositoryInterface::class)->rowsBetween(
            metric: MetricName::FeedbackSubmissions,
            dateFrom: new DateTimeImmutable('2026-06-05'),
            dateTo: new DateTimeImmutable('2026-07-09'),
        );

        expect(array_map(
            static fn ($row): string => $row->toDate()->format('Y-m-d'),
            $feedback,
        ))->toBe(['2026-06-05', '2026-07-09']);
    });

    it('rebuilds the reports over the whole stored history', function (): void {
        seedFullRebuildFixture();
        runFullRebuildJob();

        $blocks = app(AnalyticsReportRepositoryInterface::class)
            ->wholeTimeScalarValues([MetricName::PageViews, MetricName::FeedbackSubmissions]);

        $pageViewsBlock = array_values(array_filter(
            $blocks,
            static fn ($row): bool => $row->toMetric() === MetricName::PageViews,
        ));
        $feedbackBlock = array_values(array_filter(
            $blocks,
            static fn ($row): bool => $row->toMetric() === MetricName::FeedbackSubmissions,
        ));

        expect($pageViewsBlock)->toHaveCount(1)
            ->and($pageViewsBlock[0]->toValue())->toBe(14.0)
            ->and($pageViewsBlock[0]->toPeriodStart()->format('Y-m-d'))->toBe('2026-06-01')
            ->and($pageViewsBlock[0]->toPeriodEnd()->format('Y-m-d'))->toBe('2026-07-09')
            ->and($feedbackBlock)->toHaveCount(1)
            ->and($feedbackBlock[0]->toValue())->toBe(2.0);
    });

    it('recomputes the feedback history even without any events', function (): void {
        $metrics = app(AnalyticsDailyMetricRepositoryInterface::class);
        $metrics->upsertBatch([rebuildMetric('2026-06-01', 5.0)]);

        FeedbackModel::factory()->create(['created_at' => '2026-06-05 11:00:00']);

        runFullRebuildJob();

        $kept = $metrics->rowsBetween(
            metric: MetricName::PageViews,
            dateFrom: new DateTimeImmutable('2026-06-01'),
            dateTo: new DateTimeImmutable('2026-06-01'),
        );
        $feedback = $metrics->rowsBetween(
            metric: MetricName::FeedbackSubmissions,
            dateFrom: new DateTimeImmutable('2026-06-05'),
            dateTo: new DateTimeImmutable('2026-06-05'),
        );

        expect($kept)->toHaveCount(1)
            ->and($kept[0]->toValue())->toBe(5.0)
            ->and($feedback)->toHaveCount(1)
            ->and($feedback[0]->toValue())->toBe(1.0);
    });
});
