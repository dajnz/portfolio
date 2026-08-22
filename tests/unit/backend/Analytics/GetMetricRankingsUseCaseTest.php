<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\UseCases\GetMetricRankingsUseCase;
use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeAnalyticsReportDataReader;
use Tests\Support\Fakes\FakeAnalyticsReportRepository;

/**
 * The top/bottom/liked/disliked rankings (analytics.md:226, B293,
 * 11.3.4): the `span = 'all'` dimension rows capped at 10, joined with
 * the min/max metrics (time/read/completion) and the EN titles of the
 * article/project dimensions — `GET /admin/analytics/rankings?metric=
 * &variant=&scope=`.
 */

/**
 * @param list<AnalyticsReport> $rows
 * @param array<int, string> $articleTitles
 * @param array<int, string> $projectTitles
 */
function rankedRows(
    array $rows,
    string $metric,
    string $variant,
    string $scope,
    array $articleTitles = [],
    array $projectTitles = [],
): array {
    $useCase = new GetMetricRankingsUseCase(
        new FakeAnalyticsReportRepository($rows),
        new FakeAnalyticsReportDataReader(articleTitles: $articleTitles, projectTitles: $projectTitles),
    );

    return $useCase->rankings(MetricName::from($metric), $variant, $scope)['rows'];
}

it('returns the top 10 pages with the avg, min and max time on page', function (): void {
    $rows = [
        rankingsReportRow('time_on_page_seconds', 40.0, 'https://example.com/a'),
        rankingsReportRow('time_on_page_seconds', 80.0, 'https://example.com/b'),
        rankingsReportRow('time_on_page_min', 5.0, 'https://example.com/a'),
        rankingsReportRow('time_on_page_min', 10.0, 'https://example.com/b'),
        rankingsReportRow('time_on_page_max', 90.0, 'https://example.com/a'),
        rankingsReportRow('time_on_page_max', 120.0, 'https://example.com/b'),
    ];

    expect(rankedRows($rows, 'time_on_page_seconds', 'top', 'page'))->toBe([
        ['page_url' => 'https://example.com/b', 'avg' => 80.0, 'min' => 10.0, 'max' => 120.0],
        ['page_url' => 'https://example.com/a', 'avg' => 40.0, 'min' => 5.0, 'max' => 90.0],
    ]);
});

it('returns the bottom 10 pages ascending by the average', function (): void {
    $rows = [
        rankingsReportRow('time_on_page_seconds', 40.0, 'https://example.com/a'),
        rankingsReportRow('time_on_page_seconds', 20.0, 'https://example.com/c'),
    ];

    expect(rankedRows($rows, 'time_on_page_seconds', 'bottom', 'page'))->toBe([
        ['page_url' => 'https://example.com/c', 'avg' => 20.0, 'min' => 0.0, 'max' => 0.0],
        ['page_url' => 'https://example.com/a', 'avg' => 40.0, 'min' => 0.0, 'max' => 0.0],
    ]);
});

it('ranks the articles by value with their EN titles', function (): void {
    $rows = [
        rankingsReportRow('views', 50.0, 'article:5'),
        rankingsReportRow('views', 20.0, 'article:7'),
    ];

    expect(rankedRows($rows, 'views', 'top', 'article', [5 => 'Payments Gateway', 7 => 'Rust CLI']))->toBe([
        ['id' => 5, 'title' => 'Payments Gateway', 'value' => 50.0],
        ['id' => 7, 'title' => 'Rust CLI', 'value' => 20.0],
    ]);
});

it('joins the min and max into the read-time and completion rankings', function (): void {
    $rows = [
        rankingsReportRow('read_time_seconds', 60.0, 'article:5'),
        rankingsReportRow('read_time_min', 12.0, 'article:5'),
        rankingsReportRow('read_time_max', 300.0, 'article:5'),
        rankingsReportRow('completion_rate_sum', 75.0, 'article:7'),
        rankingsReportRow('completion_rate_min', 10.0, 'article:7'),
        rankingsReportRow('completion_rate_max', 100.0, 'article:7'),
    ];

    expect(rankedRows($rows, 'read_time_seconds', 'top', 'article', [5 => 'Gateway']))->toBe([
        ['id' => 5, 'title' => 'Gateway', 'avg' => 60.0, 'min' => 12.0, 'max' => 300.0],
    ])->and(rankedRows($rows, 'completion_rate_sum', 'top', 'article', [7 => 'CLI']))->toBe([
        ['id' => 7, 'title' => 'CLI', 'avg' => 75.0, 'min' => 10.0, 'max' => 100.0],
    ]);
});

it('ranks the projects by value with their EN titles', function (): void {
    $rows = [
        rankingsReportRow('views', 9.0, 'project:2'),
        rankingsReportRow('views', 4.0, 'project:3'),
    ];

    expect(rankedRows($rows, 'views', 'top', 'project', projectTitles: [2 => 'Payments', 3 => 'Portfolio']))->toBe([
        ['id' => 2, 'title' => 'Payments', 'value' => 9.0],
        ['id' => 3, 'title' => 'Portfolio', 'value' => 4.0],
    ]);
});

it('uses the top direction for the liked and disliked variants', function (): void {
    $rows = [
        rankingsReportRow('reactions_likes', 12.0, 'article:5'),
        rankingsReportRow('reactions_likes', 9.0, 'article:7'),
        rankingsReportRow('reactions_dislikes', 3.0, 'article:5'),
        rankingsReportRow('reactions_dislikes', 7.0, 'article:7'),
    ];

    expect(rankedRows($rows, 'reactions_likes', 'liked', 'article', [5 => 'A', 7 => 'B']))->toBe([
        ['id' => 5, 'title' => 'A', 'value' => 12.0],
        ['id' => 7, 'title' => 'B', 'value' => 9.0],
    ])->and(rankedRows($rows, 'reactions_dislikes', 'disliked', 'article', [5 => 'A', 7 => 'B']))->toBe([
        ['id' => 7, 'title' => 'B', 'value' => 7.0],
        ['id' => 5, 'title' => 'A', 'value' => 3.0],
    ]);
});

it('caps the rankings at 10 rows', function (): void {
    $rows = array_map(
        static fn (int $i): AnalyticsReport => rankingsReportRow('views', (float) $i, 'article:'.$i),
        range(1, 12),
    );

    $titles = array_combine(range(1, 12), array_map(static fn (int $i): string => 'A'.$i, range(1, 12)));

    expect(rankedRows($rows, 'views', 'top', 'article', $titles))->toHaveCount(10);
});

it('drops dimension rows without an EN title', function (): void {
    $rows = [
        rankingsReportRow('views', 99.0, 'article:9'),
        rankingsReportRow('views', 10.0, 'article:4'),
    ];

    expect(rankedRows($rows, 'views', 'top', 'article', [4 => 'Known']))->toBe([
        ['id' => 4, 'title' => 'Known', 'value' => 10.0],
    ]);
});

/**
 * Builds a whole-time dimension row of the given metric.
 */
function rankingsReportRow(string $metric, float $value, string $dimension): AnalyticsReport
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
