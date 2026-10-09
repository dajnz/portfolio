<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The 11.3.1 schema (database.md:513-548) on real PostgreSQL: the
 * `analytics_reports` span CHECK, both unique keys — including the
 * NULL dimension rows, which PostgreSQL treats as distinct by default
 * and would let duplicate global rows through (the `NULLS NOT
 * DISTINCT` unique constraints below prevent that) — and the removed
 * `analytics_report_frequency` setting (ADR-0017).
 */
describe('Analytics schema', function (): void {
    it('rejects an analytics_reports span outside the CHECK list', function (): void {
        expect(fn () => DB::table('analytics_reports')->insert([
            'metric' => 'page_views',
            'span' => 'fortnight',
            'dimension' => null,
            'period_start' => '2026-07-06',
            'period_end' => '2026-07-19',
            'value' => 5.0,
        ]))->toThrow(QueryException::class);
    });

    it('rejects a duplicate (metric, date, dimension) daily-metrics row', function (): void {
        DB::table('analytics_daily_metrics')->insert([
            'metric' => 'page_views',
            'date' => '2026-07-09',
            'dimension' => null,
            'value' => 1.0,
            'samples' => 1,
        ]);

        expect(fn () => DB::table('analytics_daily_metrics')->insert([
            'metric' => 'page_views',
            'date' => '2026-07-09',
            'dimension' => null,
            'value' => 2.0,
            'samples' => 2,
        ]))->toThrow(QueryException::class);
    });

    it('rejects a duplicate (metric, span, dimension, period_start, period_end) report row', function (): void {
        DB::table('analytics_reports')->insert([
            'metric' => 'page_views',
            'span' => 'day',
            'dimension' => null,
            'period_start' => '2026-07-09',
            'period_end' => '2026-07-09',
            'value' => 1.0,
        ]);

        expect(fn () => DB::table('analytics_reports')->insert([
            'metric' => 'page_views',
            'span' => 'day',
            'dimension' => null,
            'period_start' => '2026-07-09',
            'period_end' => '2026-07-09',
            'value' => 2.0,
        ]))->toThrow(QueryException::class);
    });

    it('allows the same (metric, date) across different dimensions in the daily metrics', function (): void {
        DB::table('analytics_daily_metrics')->insert([
            'metric' => 'cta_clicks',
            'date' => '2026-07-09',
            'dimension' => 'github',
            'value' => 1.0,
            'samples' => 1,
        ]);

        DB::table('analytics_daily_metrics')->insert([
            'metric' => 'cta_clicks',
            'date' => '2026-07-09',
            'dimension' => 'cv',
            'value' => 2.0,
            'samples' => 2,
        ]);

        expect(DB::table('analytics_daily_metrics')->count())->toBe(2);
    });

    it('no longer seeds the analytics_report_frequency setting (ADR-0017)', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        expect(DB::table('settings')->where('key', 'analytics_report_frequency')->exists())->toBeFalse();
    });
});
