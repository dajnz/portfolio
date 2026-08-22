<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\DTOs\AnalyticsDashboardDto;
use App\Features\Analytics\App\UseCases\GetAnalyticsDashboardUseCase;
use App\Features\Analytics\App\UseCases\GetMetricRankingsUseCase;
use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeAnalyticsReportDataReader;
use Tests\Support\Fakes\FakeAnalyticsReportRepository;

/**
 * The dashboard Inertia props (analytics.md:223-227, B293, 11.3.4):
 * the whole-time blocks per tab (overview/blog/projects), the default
 * chart series (`page_views`, day/week/month/cumulative) and the
 * default rankings (time-on-page top/bottom 10). Block keys are the
 * stable identifiers the frontend maps to i18n labels and to the
 * series/rankings metric parameters (11.3.6/11.3.7).
 */

/**
 * @param list<AnalyticsReport> $rows
 */
function dashboardProps(array $rows): AnalyticsDashboardDto
{
    $reports = new FakeAnalyticsReportRepository($rows);
    $rankings = new GetMetricRankingsUseCase($reports, new FakeAnalyticsReportDataReader());

    return (new GetAnalyticsDashboardUseCase($reports, $rankings))->get();
}

it('builds the overview blocks in order with the values, min and max', function (): void {
    $props = dashboardProps([
        dashboardBlockRow('page_views', 100.0),
        dashboardBlockRow('unique_visitors', 42.0),
        dashboardBlockRow('feedback_submissions', 7.0),
        dashboardBlockRow('time_on_page_seconds', 45.0),
        dashboardBlockRow('time_on_page_min', 3.0),
        dashboardBlockRow('time_on_page_max', 240.0),
        dashboardRankingRow('cta_clicks', 4.0, 'github'),
        dashboardRankingRow('cta_clicks', 2.0, 'demo'),
        dashboardRankingRow('cta_clicks', 1.0, 'cv'),
        dashboardRankingRow('cta_clicks', 3.0, 'feedback'),
        dashboardRankingRow('cta_clicks', 5.0, 'language'),
        dashboardRankingRow('cta_clicks', 1.0, 'theme'),
    ]);

    expect($props->overviewBlocks)->toBe([
        ['key' => 'total_visits', 'value' => 100.0],
        ['key' => 'unique_visitors', 'value' => 42.0],
        ['key' => 'cta_clicks', 'value' => 16.0],
        ['key' => 'feedback_submissions', 'value' => 7.0],
        ['key' => 'language_switches', 'value' => 5.0],
        ['key' => 'avg_time_on_page', 'value' => 45.0, 'min' => 3.0, 'max' => 240.0],
    ]);
});

it('builds the blog blocks in order — the read/completion blocks carry min and max', function (): void {
    $props = dashboardProps([
        dashboardBlockRow('blog_views', 30.0),
        dashboardBlockRow('blog_unique_visitors', 12.0),
        dashboardBlockRow('read_time_seconds', 90.0),
        dashboardBlockRow('read_time_min', 5.0),
        dashboardBlockRow('read_time_max', 300.0),
        dashboardBlockRow('completion_rate_sum', 75.0),
        dashboardBlockRow('completion_rate_min', 10.0),
        dashboardBlockRow('completion_rate_max', 100.0),
        dashboardBlockRow('reactions_likes', 14.0),
        dashboardBlockRow('reactions_dislikes', 6.0),
    ]);

    expect($props->blogBlocks)->toBe([
        ['key' => 'total_views', 'value' => 30.0],
        ['key' => 'unique_visitors', 'value' => 12.0],
        ['key' => 'avg_read_time', 'value' => 90.0, 'min' => 5.0, 'max' => 300.0],
        ['key' => 'completion_rate', 'value' => 75.0, 'min' => 10.0, 'max' => 100.0],
        ['key' => 'reactions', 'value' => 20.0],
    ]);
});

it('builds the projects blocks from the per-project views and the project-scoped uniques', function (): void {
    $props = dashboardProps([
        dashboardRankingRow('views', 8.0, 'project:1'),
        dashboardRankingRow('views', 6.0, 'project:2'),
        dashboardBlockRow('project_unique_visitors', 3.0),
    ]);

    expect($props->projectsBlocks)->toBe([
        ['key' => 'total_views', 'value' => 14.0],
        ['key' => 'unique_views', 'value' => 3.0],
    ]);
});

it('falls back to zero for metrics without stored rows', function (): void {
    $props = dashboardProps([]);

    expect($props->overviewBlocks)->toBe([
        ['key' => 'total_visits', 'value' => 0.0],
        ['key' => 'unique_visitors', 'value' => 0.0],
        ['key' => 'cta_clicks', 'value' => 0.0],
        ['key' => 'feedback_submissions', 'value' => 0.0],
        ['key' => 'language_switches', 'value' => 0.0],
        ['key' => 'avg_time_on_page', 'value' => 0.0],
    ])->and($props->blogBlocks)->toBe([
        ['key' => 'total_views', 'value' => 0.0],
        ['key' => 'unique_visitors', 'value' => 0.0],
        ['key' => 'avg_read_time', 'value' => 0.0],
        ['key' => 'completion_rate', 'value' => 0.0],
        ['key' => 'reactions', 'value' => 0.0],
    ])->and($props->projectsBlocks)->toBe([
        ['key' => 'total_views', 'value' => 0.0],
        ['key' => 'unique_views', 'value' => 0.0],
    ]);
});

it('builds the default chart with the page_views series at the four spans', function (): void {
    $props = dashboardProps([
        dashboardReportRow(MetricSpan::Day, '2026-08-19', 10.0),
        dashboardReportRow(MetricSpan::Day, '2026-08-20', 20.0),
        dashboardReportRow(MetricSpan::Week, '2026-08-17', 30.0),
        dashboardReportRow(MetricSpan::Month, '2026-08-01', 40.0),
    ]);

    expect($props->defaultChart['metric'])->toBe('page_views')
        ->and($props->defaultChart['spans']['day'])->toBe([
            'points' => [
                ['label' => '2026-08-19', 'value' => 10.0],
                ['label' => '2026-08-20', 'value' => 20.0],
            ],
        ])
        ->and($props->defaultChart['spans']['week'])->toBe([
            'points' => [['label' => '2026-08-17', 'value' => 30.0]],
        ])
        ->and($props->defaultChart['spans']['month'])->toBe([
            'points' => [['label' => '2026-08-01', 'value' => 40.0]],
        ])
        ->and($props->defaultChart['spans']['all'])->toBe([
            'points' => [
                ['label' => '2026-08-19', 'value' => 10.0],
                ['label' => '2026-08-20', 'value' => 30.0],
            ],
        ]);
});

it('builds the default rankings with the top and bottom 10 pages', function (): void {
    $rows = [
        dashboardRankingRow('time_on_page_seconds', 40.0, 'https://example.com/a'),
        dashboardRankingRow('time_on_page_seconds', 80.0, 'https://example.com/b'),
        dashboardRankingRow('time_on_page_min', 5.0, 'https://example.com/a'),
        dashboardRankingRow('time_on_page_min', 10.0, 'https://example.com/b'),
        dashboardRankingRow('time_on_page_max', 90.0, 'https://example.com/a'),
        dashboardRankingRow('time_on_page_max', 120.0, 'https://example.com/b'),
    ];

    expect(dashboardProps($rows)->defaultRankings)->toBe([
        'metric' => 'time_on_page_seconds',
        'scope' => 'page',
        'rows' => [
            'top' => [
                ['page_url' => 'https://example.com/b', 'avg' => 80.0, 'min' => 10.0, 'max' => 120.0],
                ['page_url' => 'https://example.com/a', 'avg' => 40.0, 'min' => 5.0, 'max' => 90.0],
            ],
            'bottom' => [
                ['page_url' => 'https://example.com/a', 'avg' => 40.0, 'min' => 5.0, 'max' => 90.0],
                ['page_url' => 'https://example.com/b', 'avg' => 80.0, 'min' => 10.0, 'max' => 120.0],
            ],
        ],
    ]);
});

/**
 * Builds a whole-time dimension-NULL block row of the given metric.
 */
function dashboardBlockRow(string $metric, float $value): AnalyticsReport
{
    return new AnalyticsReport(
        id: 0,
        metric: MetricName::from($metric),
        span: MetricSpan::All,
        dimension: null,
        periodStart: new DateTimeImmutable('2026-06-01'),
        periodEnd: new DateTimeImmutable('2026-08-21'),
        value: $value,
        createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
    );
}

/**
 * Builds a whole-time dimension row of the given metric.
 */
function dashboardRankingRow(string $metric, float $value, string $dimension): AnalyticsReport
{
    return new AnalyticsReport(
        id: 0,
        metric: MetricName::from($metric),
        span: MetricSpan::All,
        dimension: $dimension,
        periodStart: new DateTimeImmutable('2026-06-01'),
        periodEnd: new DateTimeImmutable('2026-08-21'),
        value: $value,
        createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
    );
}

/**
 * Builds a dimension-NULL `page_views` series row.
 */
function dashboardReportRow(MetricSpan $span, string $periodStart, float $value): AnalyticsReport
{
    return new AnalyticsReport(
        id: 0,
        metric: MetricName::PageViews,
        span: $span,
        dimension: null,
        periodStart: new DateTimeImmutable($periodStart),
        periodEnd: new DateTimeImmutable($periodStart),
        value: $value,
        createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
    );
}
