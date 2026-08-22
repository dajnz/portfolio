<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use App\Features\Analytics\Infra\Jobs\AggregateDailyMetricsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;

/**
 * The `analytics:generate-daily-metrics` command (analytics.md:182-184):
 * thin — resolves the date range (previous day + in-progress day) and
 * dispatches the queued `AggregateDailyMetricsJob`; the scheduler
 * registration in `routes/console.php` is asserted here too.
 */
describe('analytics:generate-daily-metrics command', function (): void {
    it('dispatches the aggregation job for the default range', function (): void {
        Bus::fake();

        $this->artisan('analytics:generate-daily-metrics')->assertSuccessful();

        Bus::assertDispatched(AggregateDailyMetricsJob::class, function (AggregateDailyMetricsJob $job): bool {
            [$expectedFrom, $expectedTo] = app(AggregateDailyMetricsUseCase::class)->defaultDateRange();

            return $job->dateFrom->format('Y-m-d H:i:s') === $expectedFrom->format('Y-m-d H:i:s')
                && $job->dateTo->format('Y-m-d H:i:s') === $expectedTo->format('Y-m-d H:i:s');
        });
    });

    it('is scheduled at 02:00 daily', function (): void {
        // Boots the console kernel, which loads `routes/console.php`
        // into the Schedule singleton.
        $this->artisan('list');

        $events = collect(app(Schedule::class)->events())->filter(
            fn (object $event): bool => str_contains((string) $event->command, 'analytics:generate-daily-metrics'),
        );

        expect($events)->toHaveCount(1);

        $event = $events->first();

        expect($event->expression)->toBe('0 2 * * *');
    });

    it('no longer schedules the old every-four-hours report generation', function (): void {
        $this->artisan('list');

        $events = collect(app(Schedule::class)->events())->filter(
            fn (object $event): bool => str_contains((string) $event->command, 'analytics:generate-reports'),
        );

        expect($events)->toBeEmpty();
    });
});
