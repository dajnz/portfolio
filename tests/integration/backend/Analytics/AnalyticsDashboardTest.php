<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\Infra\Models\AnalyticsReportModel;
use Database\Seeders\SettingsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

/**
 * The admin dashboard (analytics.md:196-202, inertia `analytics.yaml`):
 * every stored report, newest period first, report type ascending.
 */
it('renders the dashboard with the stored reports newest first', function (): void {
    $this->loginAsAdmin($this->createAdmin());

    AnalyticsReportModel::factory()->create([
        'report_type' => 'overall',
        'period_start' => '2026-06-29',
        'period_end' => '2026-07-05',
    ]);
    AnalyticsReportModel::factory()->create([
        'report_type' => 'blog',
        'period_start' => '2026-07-06',
        'period_end' => '2026-07-12',
        'data' => [
            'total_views' => ['value' => 42, 'previous' => null],
            'unique_visitors' => ['value' => 10, 'previous' => null],
            'top_articles' => ['columns' => ['id', 'title', 'views', 'avg_read_time', 'completion_rate'], 'rows' => []],
            'reactions' => [
                'points' => [
                    ['label' => 'likes', 'value' => 0],
                    ['label' => 'dislikes', 'value' => 0],
                ],
            ],
        ],
    ]);
    AnalyticsReportModel::factory()->create([
        'report_type' => 'overall',
        'period_start' => '2026-07-06',
        'period_end' => '2026-07-12',
    ]);

    $this->get('/admin/analytics')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Admin/Pages/AnalyticsDashboardPage')
            ->has('reports', 3)
            ->where('reports.0.report_type', 'blog')
            ->where('reports.0.period_start', '2026-07-06')
            ->where('reports.0.period_end', '2026-07-12')
            ->where('reports.0.data.total_views.value', 42)
            ->where('reports.1.report_type', 'overall')
            ->where('reports.1.period_start', '2026-07-06')
            ->where('reports.2.report_type', 'overall')
            ->where('reports.2.period_start', '2026-06-29'));
});

it('renders an empty reports list when nothing was generated yet', function (): void {
    $this->loginAsAdmin($this->createAdmin());

    $this->get('/admin/analytics')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Admin/Pages/AnalyticsDashboardPage')
            ->has('reports', 0));
});

it('redirects guests to the login page', function (): void {
    $this->get('/admin/analytics')->assertRedirect(route('login'));
});
