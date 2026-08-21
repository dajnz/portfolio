<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Infra\Jobs\AggregateDailyMetricsJob;

/**
 * The default date range of the daily aggregation (analytics.md:182-184):
 * the previous day + the in-progress day, in the server-local
 * timezone — the range shared by the scheduled command and the job.
 * The full `handle()` flow (use case + report-rebuild dispatch) is
 * covered by the integration suite (the container is required there).
 */
describe('AggregateDailyMetricsJob', function (): void {
    it('defaults to the previous day plus the in-progress day', function (): void {
        [$dateFrom, $dateTo] = AggregateDailyMetricsJob::defaultDateRange();

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $yesterday = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');

        expect($dateFrom->format('Y-m-d'))->toBe($yesterday)
            ->and($dateFrom->format('H:i:s'))->toBe('00:00:00')
            ->and($dateTo->format('Y-m-d'))->toBe($today)
            ->and($dateTo->format('H:i:s'))->toBe('23:59:59');
    });

    it('accepts an explicit range instead of the default', function (): void {
        $job = new AggregateDailyMetricsJob(
            new \DateTimeImmutable('2026-07-09 00:00:00'),
            new \DateTimeImmutable('2026-07-10 23:59:59'),
        );

        expect($job->dateFrom->format('Y-m-d'))->toBe('2026-07-09')
            ->and($job->dateTo->format('Y-m-d'))->toBe('2026-07-10');
    });
});
