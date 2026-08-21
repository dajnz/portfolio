<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\FeedbackReport;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use DateTimeImmutable;

/**
 * The `feedback` aggregation (analytics.md:179, B287/B288): the
 * submission scalar (with the previous-period value) and the per-day
 * submissions series, computed from the submission timestamps. The
 * constructor IS the aggregation (B289).
 */
describe('FeedbackReport', function (): void {
    $period = new ReportPeriod(
        new DateTimeImmutable('2026-07-06 00:00:00'),
        new DateTimeImmutable('2026-07-12 23:59:59'),
    );

    it('groups the submissions per day in ascending order', function () use ($period): void {
        $submissions = [
            new DateTimeImmutable('2026-07-09 12:00:00'),
            new DateTimeImmutable('2026-07-09 11:00:00'),
            new DateTimeImmutable('2026-07-07 09:00:00'),
        ];

        $report = new FeedbackReport($period, new DateTimeImmutable(), submissions: $submissions);

        expect($report->toScalar('total_submissions')->toValue())->toBe(3)
            ->and($report->toResults()['submissions_per_day'])->toBeInstanceOf(ReportSeriesValue::class)
            ->and($report->toResults()['submissions_per_day']->toPoints())->toBe([
                ['label' => '2026-07-07', 'value' => 1],
                ['label' => '2026-07-09', 'value' => 2],
            ]);
    });

    it('renders an empty series without submissions', function () use ($period): void {
        $report = new FeedbackReport($period, new DateTimeImmutable(), submissions: []);

        expect($report->toScalar('total_submissions')->toValue())->toBe(0)
            ->and($report->toResults()['submissions_per_day']->toPoints())->toBe([]);
    });

    it('carries the previous-period value into the scalar', function () use ($period): void {
        $previous = new FeedbackReport(
            $period,
            new DateTimeImmutable(),
            submissions: [new DateTimeImmutable('2026-07-08 10:00:00')],
        );

        $report = new FeedbackReport(
            $period,
            new DateTimeImmutable(),
            submissions: [new DateTimeImmutable('2026-07-09 10:00:00'), new DateTimeImmutable('2026-07-09 11:00:00')],
            previous: $previous,
        );

        expect($report->toScalar('total_submissions')->toPrevious())->toBe(1)
            ->and($report->toScalar('total_submissions')->toValue())->toBe(2);
    });

    it('serializes the results and restores them from the payload', function () use ($period): void {
        $report = new FeedbackReport(
            $period,
            new DateTimeImmutable(),
            submissions: [new DateTimeImmutable('2026-07-09 10:00:00')],
        );

        $restored = FeedbackReport::fromPayload(
            7,
            $period,
            array_map(static fn ($result): array => $result->toArray(), $report->toResults()),
            $report->toGeneratedAt(),
        );

        expect($restored->toId())->toBe(7)
            ->and($restored->toScalar('total_submissions')->toValue())->toBe(1);
    });
});
