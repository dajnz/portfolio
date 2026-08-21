<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use DateTimeImmutable;

/**
 * `analytics_daily_metrics` persistence (database.md:513-529, B290)
 * on real PostgreSQL: the (metric, date, dimension) upsert round-trip
 * — incl. the NULL dimension row (NULLS NOT DISTINCT unique key) —
 * the ranged reads and the date-scoped delete.
 */
/**
 * Builds a daily-metrics row entity for the repository round-trips.
 */
function analyticsMetric(
    string $date,
    float $value = 1.0,
    int $samples = 1,
    ?string $dimension = null,
    MetricName $metric = MetricName::PageViews,
): AnalyticsMetric {
    return new AnalyticsMetric(
        id: 0,
        metric: $metric,
        date: new DateTimeImmutable($date),
        dimension: $dimension,
        value: $value,
        samples: $samples,
        createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
    );
}

describe('EloquentAnalyticsDailyMetricRepository', function (): void {
    beforeEach(function (): void {
        $this->repository = app(AnalyticsDailyMetricRepositoryInterface::class);
    });

    it('upserts metrics and reads them back within the date bounds', function (): void {
        $this->repository->upsertBatch([
            new AnalyticsMetric(
                id: 0,
                metric: MetricName::PageViews,
                date: new DateTimeImmutable('2026-07-09'),
                dimension: null,
                value: 12.0,
                samples: 12,
                createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
            ),
            new AnalyticsMetric(
                id: 0,
                metric: MetricName::CtaClicks,
                date: new DateTimeImmutable('2026-07-09'),
                dimension: 'github',
                value: 3.0,
                samples: 3,
                createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
            ),
            new AnalyticsMetric(
                id: 0,
                metric: MetricName::Views,
                date: new DateTimeImmutable('2026-07-08'),
                dimension: 'article:42',
                value: 5.0,
                samples: 5,
                createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
            ),
        ]);

        $rows = $this->repository->rowsBetween(
            metric: null,
            dateFrom: new DateTimeImmutable('2026-07-08'),
            dateTo: new DateTimeImmutable('2026-07-09'),
        );

        expect($rows)->toHaveCount(3)
            ->and(array_map(fn (AnalyticsMetric $m): string => $m->toMetric()->value, $rows))->toBe([
                'views',
                'cta_clicks',
                'page_views',
            ])
            ->and($rows[2]->toDate()->format('Y-m-d'))->toBe('2026-07-09')
            ->and($rows[2]->toDimension())->toBeNull()
            ->and($rows[2]->toValue())->toBe(12.0)
            ->and($rows[2]->toSamples())->toBe(12)
            ->and($rows[2]->toCreatedAt()->format('Y-m-d H:i:s'))->toBe('2026-07-10 02:00:00');
    });

    it('re-aggregating the same (metric, date, dimension) overwrites the row', function (): void {
        $this->repository->upsertBatch([analyticsMetric('2026-07-09', 12.0, 12)]);
        $this->repository->upsertBatch([analyticsMetric('2026-07-09', 20.0, 20)]);

        $rows = $this->repository->rowsBetween(
            metric: MetricName::PageViews,
            dateFrom: new DateTimeImmutable('2026-07-09'),
            dateTo: new DateTimeImmutable('2026-07-09'),
        );

        expect($rows)->toHaveCount(1)
            ->and($rows[0]->toValue())->toBe(20.0)
            ->and($rows[0]->toSamples())->toBe(20);
    });

    it('keeps the global (NULL dimension) and entity-keyed rows distinct', function (): void {
        $this->repository->upsertBatch([
            analyticsMetric('2026-07-09', 10.0, 10, dimension: null),
            analyticsMetric('2026-07-09', 4.0, 4, dimension: 'https://example.com/'),
        ]);

        $rows = $this->repository->rowsBetween(
            metric: MetricName::PageViews,
            dateFrom: new DateTimeImmutable('2026-07-09'),
            dateTo: new DateTimeImmutable('2026-07-09'),
        );

        expect($rows)->toHaveCount(2)
            ->and(array_map(fn (AnalyticsMetric $m): ?string => $m->toDimension(), $rows))
            ->toBe(['https://example.com/', null])
            ->and($rows[0]->toValue())->toBe(4.0)
            ->and($rows[1]->toValue())->toBe(10.0);
    });

    it('filters the ranged read by metric and dimension', function (): void {
        $this->repository->upsertBatch([
            analyticsMetric('2026-07-09', 1.0, 1, dimension: 'github', metric: MetricName::CtaClicks),
            analyticsMetric('2026-07-09', 2.0, 2, dimension: 'cv', metric: MetricName::CtaClicks),
            analyticsMetric('2026-07-10', 3.0, 3, dimension: 'github', metric: MetricName::CtaClicks),
        ]);

        $rows = $this->repository->rowsBetween(
            metric: MetricName::CtaClicks,
            dateFrom: new DateTimeImmutable('2026-07-09'),
            dateTo: new DateTimeImmutable('2026-07-10'),
            dimension: 'github',
        );

        expect($rows)->toHaveCount(2)
            ->and(array_map(fn (AnalyticsMetric $m): string => $m->toDate()->format('Y-m-d'), $rows))
            ->toBe(['2026-07-09', '2026-07-10'])
            ->and(array_map(fn (AnalyticsMetric $m): float => $m->toValue(), $rows))->toBe([1.0, 3.0]);
    });

    it('deletes only the rows within the inclusive date bounds', function (): void {
        $this->repository->upsertBatch([
            analyticsMetric('2026-07-08'),
            analyticsMetric('2026-07-09'),
            analyticsMetric('2026-07-10'),
            analyticsMetric('2026-07-11'),
        ]);

        $deleted = $this->repository->deleteForDates(
            new DateTimeImmutable('2026-07-09'),
            new DateTimeImmutable('2026-07-10'),
        );

        expect($deleted)->toBe(2);

        $remaining = $this->repository->rowsBetween(
            metric: null,
            dateFrom: new DateTimeImmutable('2026-07-08'),
            dateTo: new DateTimeImmutable('2026-07-11'),
        );

        expect(array_map(
            static fn (AnalyticsMetric $m): string => $m->toDate()->format('Y-m-d'),
            $remaining,
        ))->toBe(['2026-07-08', '2026-07-11']);
    });

    it('reports the distinct covered dates ascending', function (): void {
        $this->repository->upsertBatch([
            analyticsMetric('2026-07-09'),
            analyticsMetric('2026-07-10'),
            analyticsMetric('2026-07-10', 1.0, 1, dimension: 'cv'),
            analyticsMetric('2026-07-08'),
        ]);

        $dates = $this->repository->datesCovered();

        expect(array_map(
            static fn (DateTimeImmutable $date): string => $date->format('Y-m-d'),
            $dates,
        ))->toBe(['2026-07-08', '2026-07-09', '2026-07-10']);
    });

    it('returns an empty read when nothing is stored', function (): void {
        expect($this->repository->rowsBetween(
            metric: null,
            dateFrom: new DateTimeImmutable('2026-07-01'),
            dateTo: new DateTimeImmutable('2026-07-31'),
        ))->toBe([])
            ->and($this->repository->datesCovered())->toBe([]);
    });
});
