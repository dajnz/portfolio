<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `analytics_daily_metrics` row entity (database.md:513-529,
 * ADR-0017): the weighted sum/samples pair and the dimension scoping, with
 * the constructor invariants — samples at least 1, finite value,
 * dimension within the varchar(500) column.
 */
describe('AnalyticsMetric', function (): void {
    it('exposes the stored fields', function (): void {
        $metric = new AnalyticsMetric(
            id: 7,
            metric: MetricName::TimeOnPageSeconds,
            date: new DateTimeImmutable('2026-07-09'),
            dimension: null,
            value: 160.5,
            samples: 4,
            createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
        );

        expect($metric->toId())->toBe(7)
            ->and($metric->toMetric())->toBe(MetricName::TimeOnPageSeconds)
            ->and($metric->toDate())->toEqual(new DateTimeImmutable('2026-07-09'))
            ->and($metric->toDimension())->toBeNull()
            ->and($metric->toValue())->toBe(160.5)
            ->and($metric->toSamples())->toBe(4)
            ->and($metric->toCreatedAt())->toEqual(new DateTimeImmutable('2026-07-10 02:00:00'));
    });

    it('carries the entity-key dimension (page URL, article, project, CTA target)', function (): void {
        $metric = new AnalyticsMetric(
            id: 0,
            metric: MetricName::CtaClicks,
            date: new DateTimeImmutable('2026-07-09'),
            dimension: 'github',
            value: 3.0,
            samples: 3,
            createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
        );

        expect($metric->toDimension())->toBe('github');
    });

    it('rejects a sample count below 1', function (): void {
        expect(fn () => new AnalyticsMetric(
            id: 0,
            metric: MetricName::PageViews,
            date: new DateTimeImmutable('2026-07-09'),
            dimension: null,
            value: 1.0,
            samples: 0,
            createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Analytics metric samples must be at least 1.');
    });

    it('rejects a non-finite value', function (): void {
        expect(fn () => new AnalyticsMetric(
            id: 0,
            metric: MetricName::PageViews,
            date: new DateTimeImmutable('2026-07-09'),
            dimension: null,
            value: INF,
            samples: 1,
            createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Analytics metric value must be finite.');
    });

    it('rejects a dimension longer than the varchar(500) column', function (): void {
        expect(fn () => new AnalyticsMetric(
            id: 0,
            metric: MetricName::Views,
            date: new DateTimeImmutable('2026-07-09'),
            dimension: str_repeat('u', AnalyticsMetric::MAX_DIMENSION_LENGTH + 1),
            value: 1.0,
            samples: 1,
            createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Analytics metric dimension must not exceed 500 characters.');

        expect(fn () => new AnalyticsMetric(
            id: 0,
            metric: MetricName::Views,
            date: new DateTimeImmutable('2026-07-09'),
            dimension: str_repeat('u', AnalyticsMetric::MAX_DIMENSION_LENGTH),
            value: 1.0,
            samples: 1,
            createdAt: new DateTimeImmutable('2026-07-10 02:00:00'),
        ))->not->toThrow(InvalidArgumentException::class);
    });
});
