<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * `analytics_reports` persistence on real PostgreSQL: the (metric, span,
 * dimension, period_start, period_end) upsert — incl. the NULL
 * dimension rows (NULLS NOT DISTINCT unique key) — and the dashboard
 * read shapes: whole-time blocks, last-N series, top/bottom rankings
 * and the available spans per metric.
 */
describe('EloquentAnalyticsReportRepository', function (): void {
    beforeEach(function (): void {
        $this->repository = app(AnalyticsReportRepositoryInterface::class);
    });

    it('stores the dimension-NULL rows for the blocks and series', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('page_views', MetricSpan::All, '2026-06-01', '2026-08-21', 100.0),
            analyticsReport('page_views', MetricSpan::Day, '2026-08-19', '2026-08-19', 10.0),
            analyticsReport('page_views', MetricSpan::Day, '2026-08-20', '2026-08-20', 20.0),
        ]);

        $blocks = $this->repository->wholeTimeScalarValues([MetricName::PageViews]);
        $series = $this->repository->series(MetricName::PageViews, MetricSpan::Day, 2);

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]->toSpan())->toBe(MetricSpan::All)
            ->and($blocks[0]->toValue())->toBe(100.0);

        expect($series)->toHaveCount(2)
            ->and(array_map(
                static fn (AnalyticsReport $r): string => $r->toPeriodStart()->format('Y-m-d'),
                $series,
            ))->toBe(['2026-08-20', '2026-08-19']);
    });

    it('upserts on the unique key — re-aggregating a period overwrites the value', function (): void {
        $this->repository->upsertBatch([analyticsReport('page_views', MetricSpan::All, '2026-06-01', '2026-08-21', 100.0)]);
        $this->repository->upsertBatch([analyticsReport('page_views', MetricSpan::All, '2026-06-01', '2026-08-21', 150.0)]);

        $blocks = $this->repository->wholeTimeScalarValues([MetricName::PageViews]);

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]->toValue())->toBe(150.0);
    });

    it('keeps the NULL dimension and entity-keyed rows of the same period distinct', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 9.0),
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 5.0, 'article:42'),
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 4.0, 'article:7'),
        ]);

        $rankings = $this->repository->rankings(MetricName::Views, 'top', 10);

        expect($rankings)->toHaveCount(2)
            ->and(array_map(
                static fn (AnalyticsReport $r): ?string => $r->toDimension(),
                $rankings,
            ))->toBe(['article:42', 'article:7']);
    });

    it('orders the rankings by value — top descending, bottom ascending, capped by the limit', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('time_on_page_seconds', MetricSpan::All, '2026-06-01', '2026-08-21', 40.0, 'https://example.com/a'),
            analyticsReport('time_on_page_seconds', MetricSpan::All, '2026-06-01', '2026-08-21', 80.0, 'https://example.com/b'),
            analyticsReport('time_on_page_seconds', MetricSpan::All, '2026-06-01', '2026-08-21', 20.0, 'https://example.com/c'),
        ]);

        $top = $this->repository->rankings(MetricName::TimeOnPageSeconds, 'top', 2);
        $bottom = $this->repository->rankings(MetricName::TimeOnPageSeconds, 'bottom', 2);

        expect(array_map(static fn (AnalyticsReport $r): float => $r->toValue(), $top))->toBe([80.0, 40.0])
            ->and(array_map(static fn (AnalyticsReport $r): float => $r->toValue(), $bottom))->toBe([20.0, 40.0]);
    });

    it('breaks ranking ties deterministically by dimension', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 10.0, 'project:beta'),
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 10.0, 'project:alpha'),
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 10.0, 'project:gamma'),
        ]);

        $rankings = $this->repository->rankings(MetricName::Views, 'top', 10);

        expect(array_map(
            static fn (AnalyticsReport $r): ?string => $r->toDimension(),
            $rankings,
        ))->toBe(['project:alpha', 'project:beta', 'project:gamma']);
    });

    it('rejects an unknown ranking direction', function (): void {
        expect(fn () => $this->repository->rankings(MetricName::PageViews, 'sideways', 10))
            ->toThrow(InvalidArgumentException::class, 'Unknown ranking direction [sideways].');
    });

    it('returns the blocks only for the requested metrics, span=all and dimension NULL', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('page_views', MetricSpan::All, '2026-06-01', '2026-08-21', 100.0),
            analyticsReport('unique_visitors', MetricSpan::All, '2026-06-01', '2026-08-21', 30.0),
            analyticsReport('feedback_submissions', MetricSpan::All, '2026-06-01', '2026-08-21', 7.0),
            analyticsReport('page_views', MetricSpan::Month, '2026-07-01', '2026-07-31', 40.0),
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 9.0, 'article:42'),
        ]);

        $blocks = $this->repository->wholeTimeScalarValues([MetricName::PageViews, MetricName::UniqueVisitors]);

        expect($blocks)->toHaveCount(2)
            ->and(array_map(static fn (AnalyticsReport $r): string => $r->toMetric()->value, $blocks))
            ->toBe(['page_views', 'unique_visitors']);
    });

    it('reports the distinct spans for a metric in the span enumeration order', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('page_views', MetricSpan::Week, '2026-06-29', '2026-07-05', 50.0),
            analyticsReport('page_views', MetricSpan::Day, '2026-08-20', '2026-08-20', 20.0),
            analyticsReport('page_views', MetricSpan::Month, '2026-07-01', '2026-07-31', 120.0),
            analyticsReport('unique_visitors', MetricSpan::All, '2026-06-01', '2026-08-21', 30.0),
        ]);

        expect($this->repository->metricSpans(MetricName::PageViews))
            ->toBe([MetricSpan::Day, MetricSpan::Week, MetricSpan::Month])
            ->and($this->repository->metricSpans(MetricName::UniqueVisitors))
            ->toBe([MetricSpan::All])
            ->and($this->repository->metricSpans(MetricName::CtaClicks))->toBe([]);
    });

    it('replaces the whole table with the given reports', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('page_views', MetricSpan::All, '2026-06-01', '2026-08-21', 100.0),
            analyticsReport('unique_visitors', MetricSpan::All, '2026-06-01', '2026-08-21', 30.0),
        ]);

        $this->repository->replaceAll([
            analyticsReport('page_views', MetricSpan::All, '2026-06-01', '2026-08-21', 200.0),
            analyticsReport('page_views', MetricSpan::Day, '2026-08-20', '2026-08-20', 25.0),
        ]);

        $blocks = $this->repository->wholeTimeScalarValues([MetricName::PageViews, MetricName::UniqueVisitors]);
        $series = $this->repository->series(MetricName::PageViews, MetricSpan::Day, 10);

        expect($blocks)->toHaveCount(1)
            ->and($blocks[0]->toValue())->toBe(200.0)
            ->and($series)->toHaveCount(1)
            ->and($series[0]->toValue())->toBe(25.0);
    });

    it('returns empty reads when nothing is stored', function (): void {
        expect($this->repository->wholeTimeScalarValues([MetricName::PageViews]))->toBe([])
            ->and($this->repository->series(MetricName::PageViews, MetricSpan::Day, 10))->toBe([])
            ->and($this->repository->rankings(MetricName::PageViews, 'top', 10))->toBe([])
            ->and($this->repository->metricSpans(MetricName::PageViews))->toBe([])
            ->and($this->repository->cumulativeSeries(MetricName::PageViews))->toBe([]);
    });

    it('computes the cumulative running total over the day rows', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('page_views', MetricSpan::Day, '2026-08-19', '2026-08-19', 10.0),
            analyticsReport('page_views', MetricSpan::Day, '2026-08-21', '2026-08-21', 30.0),
            analyticsReport('page_views', MetricSpan::Day, '2026-08-20', '2026-08-20', 20.0),
            // The other spans and the dimension rows must not contribute.
            analyticsReport('page_views', MetricSpan::Week, '2026-08-17', '2026-08-23', 60.0),
            analyticsReport('page_views', MetricSpan::All, '2026-06-01', '2026-08-21', 60.0),
            analyticsReport('views', MetricSpan::Day, '2026-08-20', '2026-08-20', 5.0, 'article:42'),
        ]);

        $series = $this->repository->cumulativeSeries(MetricName::PageViews);

        expect($series)->toHaveCount(3)
            ->and(array_map(
                static fn (AnalyticsReport $r): string => $r->toPeriodStart()->format('Y-m-d'),
                $series,
            ))->toBe(['2026-08-19', '2026-08-20', '2026-08-21'])
            ->and(array_map(
                static fn (AnalyticsReport $r): float => $r->toValue(),
                $series,
            ))->toBe([10.0, 30.0, 60.0])
            ->and($series[0]->toSpan())->toBe(MetricSpan::Day)
            ->and($series[0]->toDimension())->toBeNull();
    });
});

function analyticsReport(
    string $metric,
    MetricSpan $span,
    string $periodStart,
    string $periodEnd,
    float $value,
    ?string $dimension = null,
): AnalyticsReport {
    return new AnalyticsReport(
        id: 0,
        metric: MetricName::from($metric),
        span: $span,
        dimension: $dimension,
        periodStart: new DateTimeImmutable($periodStart),
        periodEnd: new DateTimeImmutable($periodEnd),
        value: $value,
        createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
    );
}

describe('dashboard reads (11.3.4)', function (): void {
    beforeEach(function (): void {
        $this->repository = app(AnalyticsReportRepositoryInterface::class);
    });

    it('filters the series to the exact dimension — the per-target cta_clicks rows', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('cta_clicks', MetricSpan::Week, '2026-08-17', '2026-08-23', 5.0, 'github'),
            analyticsReport('cta_clicks', MetricSpan::Week, '2026-08-17', '2026-08-23', 3.0, 'language'),
        ]);

        $series = $this->repository->series(MetricName::CtaClicks, MetricSpan::Week, 10, 'language');

        expect($series)->toHaveCount(1)
            ->and($series[0]->toDimension())->toBe('language')
            ->and($series[0]->toValue())->toBe(3.0);
    });

    it('filters the cumulative series to the given dimension — the per-target cta_clicks dynamics', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('cta_clicks', MetricSpan::Day, '2026-08-19', '2026-08-19', 3.0, 'github'),
            analyticsReport('cta_clicks', MetricSpan::Day, '2026-08-20', '2026-08-20', 4.0, 'github'),
            analyticsReport('cta_clicks', MetricSpan::Day, '2026-08-19', '2026-08-19', 2.0, 'language'),
            analyticsReport('cta_clicks', MetricSpan::Day, '2026-08-20', '2026-08-20', 6.0, 'language'),
        ]);

        $series = $this->repository->cumulativeSeries(MetricName::CtaClicks, 'github');

        expect($series)->toHaveCount(2)
            ->and(array_map(
                static fn (AnalyticsReport $r): string => $r->toPeriodStart()->format('Y-m-d'),
                $series,
            ))->toBe(['2026-08-19', '2026-08-20'])
            ->and(array_map(
                static fn (AnalyticsReport $r): float => $r->toValue(),
                $series,
            ))->toBe([3.0, 7.0])
            ->and($series[0]->toDimension())->toBe('github');
    });

    it('filters the rankings by the dimension prefix — the article/project families', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 9.0, 'project:2'),
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 50.0, 'article:5'),
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 20.0, 'article:7'),
        ]);

        $articles = $this->repository->rankings(MetricName::Views, 'top', 10, 'article:');
        $projects = $this->repository->rankings(MetricName::Views, 'top', 10, 'project:');

        expect(array_map(
            static fn (AnalyticsReport $r): ?string => $r->toDimension(),
            $articles,
        ))->toBe(['article:5', 'article:7'])
            ->and(array_map(
                static fn (AnalyticsReport $r): ?string => $r->toDimension(),
                $projects,
            ))->toBe(['project:2']);
    });

    it('returns the whole-time dimension rows for the block totals — dimension ascending', function (): void {
        $this->repository->upsertBatch([
            analyticsReport('cta_clicks', MetricSpan::All, '2026-06-01', '2026-08-21', 4.0, 'github'),
            analyticsReport('cta_clicks', MetricSpan::All, '2026-06-01', '2026-08-21', 5.0, 'language'),
            analyticsReport('cta_clicks', MetricSpan::All, '2026-06-01', '2026-08-21', 1.0, 'theme'),
            analyticsReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 8.0, 'project:1'),
        ]);

        $targets = $this->repository->dimensionRows(MetricName::CtaClicks);
        $projects = $this->repository->dimensionRows(MetricName::Views, 'project:');

        expect(array_map(
            static fn (AnalyticsReport $r): ?string => $r->toDimension(),
            $targets,
        ))->toBe(['github', 'language', 'theme'])
            ->and(array_map(
                static fn (AnalyticsReport $r): float => $r->toValue(),
                $projects,
            ))->toBe([8.0]);
    });
});
