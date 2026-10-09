<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\Infra\Jobs\RebuildReportsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;

/**
 * The `analytics:generate-reports` command: a manual full rebuild —
 * thin, dispatches `RebuildReportsJob` in the full-rebuild mode
 * (recompute the event-window metrics first, then rebuild every report
 * span); it is NOT scheduled.
 */
describe('analytics:generate-reports command', function (): void {
    it('dispatches the full-rebuild job', function (): void {
        Bus::fake();

        $this->artisan('analytics:generate-reports')->assertSuccessful();

        Bus::assertDispatched(RebuildReportsJob::class, function (RebuildReportsJob $job): bool {
            return $job->recomputeMetrics === true;
        });
    });

    it('is not scheduled', function (): void {
        // Boots the console kernel, which loads `routes/console.php`
        // into the Schedule singleton.
        $this->artisan('list');

        $events = collect(app(Schedule::class)->events())->filter(
            fn (object $event): bool => str_contains((string) $event->command, 'analytics:generate-reports'),
        );

        expect($events)->toBeEmpty();
    });
});
