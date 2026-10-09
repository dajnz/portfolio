<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use DateTimeImmutable;
use InvalidArgumentException;
use ValueError;

/**
 * The reworked `analytics_reports` row entity (database.md:531-548,
 * ADR-0017): per-metric aggregates over the daily metrics for one span —
 * ordered period bounds, finite value, dimension within the
 * varchar(500) column — plus the `fromRow()` row materialization.
 */
describe('AnalyticsReport', function (): void {
    it('exposes the stored fields', function (): void {
        $report = new AnalyticsReport(
            id: 12,
            metric: MetricName::PageViews,
            span: MetricSpan::Week,
            dimension: null,
            periodStart: new DateTimeImmutable('2026-06-29'),
            periodEnd: new DateTimeImmutable('2026-07-05'),
            value: 128.0,
            createdAt: new DateTimeImmutable('2026-07-06 02:00:00'),
        );

        expect($report->toId())->toBe(12)
            ->and($report->toMetric())->toBe(MetricName::PageViews)
            ->and($report->toSpan())->toBe(MetricSpan::Week)
            ->and($report->toDimension())->toBeNull()
            ->and($report->toPeriodStart())->toEqual(new DateTimeImmutable('2026-06-29'))
            ->and($report->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-07-05'))
            ->and($report->toValue())->toBe(128.0)
            ->and($report->toCreatedAt())->toEqual(new DateTimeImmutable('2026-07-06 02:00:00'));
    });

    it('materializes a stored row via fromRow', function (): void {
        $report = AnalyticsReport::fromRow([
            'id' => 3,
            'metric' => 'views',
            'span' => 'all',
            'dimension' => 'article:42',
            'period_start' => '2026-06-01',
            'period_end' => '2026-08-21',
            'value' => 84.0,
            'created_at' => '2026-08-21 02:00:00',
        ]);

        expect($report->toId())->toBe(3)
            ->and($report->toMetric())->toBe(MetricName::Views)
            ->and($report->toSpan())->toBe(MetricSpan::All)
            ->and($report->toDimension())->toBe('article:42')
            ->and($report->toPeriodStart()->format('Y-m-d'))->toBe('2026-06-01')
            ->and($report->toPeriodEnd()->format('Y-m-d'))->toBe('2026-08-21')
            ->and($report->toValue())->toBe(84.0);
    });

    it('rejects a period start after its end', function (): void {
        expect(fn () => new AnalyticsReport(
            id: 0,
            metric: MetricName::PageViews,
            span: MetricSpan::Day,
            dimension: null,
            periodStart: new DateTimeImmutable('2026-07-06'),
            periodEnd: new DateTimeImmutable('2026-07-05'),
            value: 1.0,
            createdAt: new DateTimeImmutable('2026-07-06 02:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Analytics report period start must not be after its end.');
    });

    it('rejects a non-finite value', function (): void {
        expect(fn () => new AnalyticsReport(
            id: 0,
            metric: MetricName::PageViews,
            span: MetricSpan::All,
            dimension: null,
            periodStart: new DateTimeImmutable('2026-06-01'),
            periodEnd: new DateTimeImmutable('2026-08-21'),
            value: NAN,
            createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Analytics report value must be finite.');
    });

    it('rejects a dimension longer than the varchar(500) column', function (): void {
        expect(fn () => new AnalyticsReport(
            id: 0,
            metric: MetricName::Views,
            span: MetricSpan::All,
            dimension: str_repeat('u', AnalyticsReport::MAX_DIMENSION_LENGTH + 1),
            periodStart: new DateTimeImmutable('2026-06-01'),
            periodEnd: new DateTimeImmutable('2026-08-21'),
            value: 1.0,
            createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
        ))->toThrow(InvalidArgumentException::class, 'Analytics report dimension must not exceed 500 characters.');

        expect(fn () => new AnalyticsReport(
            id: 0,
            metric: MetricName::Views,
            span: MetricSpan::All,
            dimension: str_repeat('u', AnalyticsReport::MAX_DIMENSION_LENGTH),
            periodStart: new DateTimeImmutable('2026-06-01'),
            periodEnd: new DateTimeImmutable('2026-08-21'),
            value: 1.0,
            createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
        ))->not->toThrow(InvalidArgumentException::class);
    });

    it('rejects a row with an unknown metric or span', function (): void {
        expect(fn () => AnalyticsReport::fromRow([
            'id' => 1,
            'metric' => 'bogus_metric',
            'span' => 'all',
            'dimension' => null,
            'period_start' => '2026-06-01',
            'period_end' => '2026-08-21',
            'value' => 1.0,
            'created_at' => '2026-08-21 02:00:00',
        ]))->toThrow(ValueError::class);

        expect(fn () => AnalyticsReport::fromRow([
            'id' => 1,
            'metric' => 'page_views',
            'span' => 'bogus_span',
            'dimension' => null,
            'period_start' => '2026-06-01',
            'period_end' => '2026-08-21',
            'value' => 1.0,
            'created_at' => '2026-08-21 02:00:00',
        ]))->toThrow(ValueError::class);
    });
});
