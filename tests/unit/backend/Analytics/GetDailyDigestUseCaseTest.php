<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\UseCases\GetDailyDigestUseCase;
use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeAnalyticsDailyMetricRepository;
use Tests\Support\Fakes\FakeAnalyticsReportDataReader;

/**
 * The email digest payload (analytics.md:214-219, B294, 11.3.5): the
 * previous day's key metrics — page views, unique visitors, feedback
 * submissions — and the top article by views (the `views` rows with an
 * `article:{id}` dimension), picked with its EN title from the reader.
 */

function digestMetricRow(MetricName $metric, float $value, ?string $dimension = null): AnalyticsMetric
{
    return new AnalyticsMetric(
        id: 0,
        metric: $metric,
        date: new DateTimeImmutable('2026-07-09'),
        dimension: $dimension,
        value: $value,
        samples: 1,
        createdAt: new DateTimeImmutable(),
    );
}

/**
 * @param list<AnalyticsMetric> $rows
 * @param array<int, string> $articleTitles
 */
function dailyDigest(array $rows, array $articleTitles = []): object
{
    $useCase = new GetDailyDigestUseCase(
        new FakeAnalyticsDailyMetricRepository($rows),
        new FakeAnalyticsReportDataReader(articleTitles: $articleTitles),
    );

    return $useCase->digest(new DateTimeImmutable('2026-07-09'));
}

it("reads the day's global key metrics", function (): void {
    $digest = dailyDigest([
        digestMetricRow(MetricName::PageViews, 3.0),
        digestMetricRow(MetricName::UniqueVisitors, 2.0),
        digestMetricRow(MetricName::FeedbackSubmissions, 5.0),
    ]);

    expect($digest->day->format('Y-m-d'))->toBe('2026-07-09')
        ->and($digest->pageViews)->toBe(3.0)
        ->and($digest->uniqueVisitors)->toBe(2.0)
        ->and($digest->feedbackSubmissions)->toBe(5.0)
        ->and($digest->topArticle)->toBeNull();
});

it('zeroes the metrics and omits the article when the day has no rows', function (): void {
    $digest = dailyDigest([]);

    expect($digest->pageViews)->toBe(0.0)
        ->and($digest->uniqueVisitors)->toBe(0.0)
        ->and($digest->feedbackSubmissions)->toBe(0.0)
        ->and($digest->topArticle)->toBeNull();
});

it('picks the top article by views with its EN title', function (): void {
    $digest = dailyDigest(
        [
            digestMetricRow(MetricName::Views, 20.0, 'article:7'),
            digestMetricRow(MetricName::Views, 50.0, 'article:5'),
            digestMetricRow(MetricName::Views, 40.0, 'project:7'),
        ],
        articleTitles: [5 => 'Payments Gateway', 7 => 'Rust CLI'],
    );

    expect($digest->topArticle)->not->toBeNull();
    expect($digest->topArticle->id)->toBe(5)
        ->and($digest->topArticle->title)->toBe('Payments Gateway')
        ->and($digest->topArticle->views)->toBe(50.0);
});

it('skips untranslated articles and falls back to the next best', function (): void {
    $digest = dailyDigest(
        [
            digestMetricRow(MetricName::Views, 50.0, 'article:5'),
            digestMetricRow(MetricName::Views, 30.0, 'article:7'),
        ],
        articleTitles: [7 => 'Rust CLI'],
    );

    expect($digest->topArticle)->not->toBeNull();
    expect($digest->topArticle->id)->toBe(7)
        ->and($digest->topArticle->views)->toBe(30.0);
});

it('omits the article when no article row has an EN translation', function (): void {
    $digest = dailyDigest(
        [digestMetricRow(MetricName::Views, 50.0, 'article:5')],
        articleTitles: [],
    );

    expect($digest->topArticle)->toBeNull();
});
