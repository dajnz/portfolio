<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\Infra\Models\AnalyticsDailyMetricModel;
use App\Features\Analytics\Infra\Models\AnalyticsEventModel;
use App\Features\Analytics\Infra\Models\AnalyticsReportModel;

/**
 * `analytics:wipe` (manual testing aid): deletes ALL raw events, daily
 * metrics and reports — the "fresh state" reset. Interactive terminals
 * get a confirmation prompt (skipped by `--force`, the
 * ResetAdminPasswordCommand pattern); the command reports the per-table
 * deleted counts.
 */
describe('analytics:wipe command', function (): void {
    it('deletes every analytics event, metric and report row with --force', function (): void {
        AnalyticsEventModel::factory()->count(3)->create();
        AnalyticsDailyMetricModel::factory()->count(2)->create();
        AnalyticsReportModel::factory()->count(4)->create();

        $this->artisan('analytics:wipe', ['--force' => true])
            ->expectsOutputToContain('Deleted 3 event(s), 2 metric row(s), 4 report row(s).')
            ->assertSuccessful();

        expect(AnalyticsEventModel::query()->count())->toBe(0)
            ->and(AnalyticsDailyMetricModel::query()->count())->toBe(0)
            ->and(AnalyticsReportModel::query()->count())->toBe(0);
    });

    it('aborts on a negative confirmation answer and keeps the data', function (): void {
        AnalyticsEventModel::factory()->count(3)->create();

        $this->artisan('analytics:wipe')
            ->expectsConfirmation('Delete ALL analytics events, daily metrics and reports?', 'no')
            ->expectsOutputToContain('Cancelled.')
            ->assertSuccessful();

        expect(AnalyticsEventModel::query()->count())->toBe(3);
    });

    it('wipes an already-empty analytics store cleanly', function (): void {
        $this->artisan('analytics:wipe', ['--force' => true])
            ->expectsOutputToContain('Deleted 0 event(s), 0 metric row(s), 0 report row(s).')
            ->assertSuccessful();
    });
});
