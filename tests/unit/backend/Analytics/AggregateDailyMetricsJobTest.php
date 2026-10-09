<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Infra\Jobs\AggregateDailyMetricsJob;

describe('AggregateDailyMetricsJob', function (): void {
    it('accepts an explicit range instead of the default', function (): void {
        $job = new AggregateDailyMetricsJob(
            new \DateTimeImmutable('2026-07-09 00:00:00'),
            new \DateTimeImmutable('2026-07-10 23:59:59'),
        );

        expect($job->dateFrom->format('Y-m-d'))->toBe('2026-07-09')
            ->and($job->dateTo->format('Y-m-d'))->toBe('2026-07-10');
    });

    it('defaults to null dates — the use case resolves the range', function (): void {
        $job = new AggregateDailyMetricsJob();

        expect($job->dateFrom)->toBeNull()
            ->and($job->dateTo)->toBeNull();
    });
});
