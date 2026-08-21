<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Enums\ReportFrequency;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calendar-aligned period math (analytics.md:164-172, B219): a day runs
 * 00:00→23:59:59, a week Monday→Sunday, a month the 1st→last day; the
 * `previous` period is the just-completed one.
 */
describe('ReportFrequency', function (): void {
    it('computes the daily current period', function (): void {
        $period = ReportFrequency::Daily->currentPeriod(
            new DateTimeImmutable('2026-07-15 14:30:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2026-07-15 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-07-15 23:59:59'));
    });

    it('computes the daily previous period', function (): void {
        $period = ReportFrequency::Daily->previousPeriod(
            new DateTimeImmutable('2026-07-15 00:30:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2026-07-14 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-07-14 23:59:59'));
    });

    it('computes the weekly current period — Monday to Sunday', function (): void {
        $period = ReportFrequency::Weekly->currentPeriod(
            new DateTimeImmutable('2026-07-15 14:30:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2026-07-13 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-07-19 23:59:59'));
    });

    it('keeps a Sunday inside its own week', function (): void {
        $period = ReportFrequency::Weekly->currentPeriod(
            new DateTimeImmutable('2026-07-19 10:00:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2026-07-13 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-07-19 23:59:59'));
    });

    it('computes the weekly previous period', function (): void {
        $period = ReportFrequency::Weekly->previousPeriod(
            new DateTimeImmutable('2026-07-13 00:30:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2026-07-06 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-07-12 23:59:59'));
    });

    it('crosses the year boundary for weekly periods', function (): void {
        $period = ReportFrequency::Weekly->currentPeriod(
            new DateTimeImmutable('2026-01-02 10:00:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2025-12-29 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-01-04 23:59:59'));
    });

    it('computes the monthly current period — first to last day', function (): void {
        $period = ReportFrequency::Monthly->currentPeriod(
            new DateTimeImmutable('2026-07-15 14:30:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2026-07-01 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-07-31 23:59:59'));
    });

    it('computes the monthly previous period', function (): void {
        $period = ReportFrequency::Monthly->previousPeriod(
            new DateTimeImmutable('2026-07-01 00:30:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2026-06-01 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-06-30 23:59:59'));
    });

    it('crosses the year boundary for monthly periods', function (): void {
        $period = ReportFrequency::Monthly->previousPeriod(
            new DateTimeImmutable('2027-01-15 10:00:00'),
        );

        expect($period->toPeriodStart())->toEqual(new DateTimeImmutable('2026-12-01 00:00:00'))
            ->and($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2026-12-31 23:59:59'));
    });

    it('handles leap-year February', function (): void {
        $period = ReportFrequency::Monthly->currentPeriod(
            new DateTimeImmutable('2028-02-15 10:00:00'),
        );

        expect($period->toPeriodEnd())->toEqual(new DateTimeImmutable('2028-02-29 23:59:59'));
    });

    it('resolves frequencies from their string values', function (): void {
        expect(ReportFrequency::fromString('daily'))->toBe(ReportFrequency::Daily)
            ->and(ReportFrequency::fromString('weekly'))->toBe(ReportFrequency::Weekly)
            ->and(ReportFrequency::fromString('monthly'))->toBe(ReportFrequency::Monthly);
    });

    it('rejects an unknown frequency', function (): void {
        expect(fn () => ReportFrequency::fromString('yearly'))
            ->toThrow(InvalidArgumentException::class, 'daily, weekly, monthly');
    });
});

describe('ReportPeriod', function (): void {
    it('rejects a start after the end', function (): void {
        expect(fn () => new ReportPeriod(
            new DateTimeImmutable('2026-07-13 00:00:00'),
            new DateTimeImmutable('2026-07-12 23:59:59'),
        ))->toThrow(InvalidArgumentException::class, 'must not be after its end');
    });

    it('exposes the exclusive upper bound one second past the end', function (): void {
        $period = new ReportPeriod(
            new DateTimeImmutable('2026-07-09 00:00:00'),
            new DateTimeImmutable('2026-07-09 23:59:59'),
        );

        expect($period->toPeriodEndExclusive())->toEqual(new DateTimeImmutable('2026-07-10 00:00:00'));
    });
});
