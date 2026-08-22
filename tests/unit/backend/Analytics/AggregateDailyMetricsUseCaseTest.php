<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Entities\ArticleReadEvent;
use App\Features\Analytics\Domain\Entities\ArticleReactionEvent;
use App\Features\Analytics\Domain\Entities\CallToActionClickEvent;
use App\Features\Analytics\Domain\Entities\LanguageSwitchEvent;
use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Entities\ThemeSwitchEvent;
use App\Features\Analytics\Domain\Entities\TimeOnPageEvent;
use App\Features\Analytics\Domain\Enums\CallToActionTarget;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Domain\Enums\SocialReaction;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Tests\Support\AnalyticsEventFixture;
use Tests\Support\Fakes\FakeAnalyticsDailyMetricRepository;
use Tests\Support\Fakes\FakeAnalyticsEventRepository;
use Tests\Support\Fakes\FakeAnalyticsReportDataReader;

/**
 * The per-day aggregation (analytics.md:158-180, B292): the full
 * metric catalog computed from a day's events (+ feedback rows) — the
 * weighted sum/samples pair, the min/max rows, the per-dimension rows
 * (CTA targets, page URLs, `article:{id}`, `project:{id}`), the
 * distinct-ip visitors and the feedback submissions count — all
 * upserted via the daily-metric repository. The day boundaries are
 * server-local; a range spans several days, each aggregated
 * independently.
 */

/**
 * The shared single-day fixture: the events mirror the real frontend
 * payloads (B287/B289 — the page context is the attribution source).
 *
 * @return list<\App\Features\Analytics\Domain\Entities\AnalyticsEvent>
 */
function dayEvents(): array
{
    return [
        new PageViewEvent(
            id: 1,
            clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::Landing, null, 'https://example.com/'),
            referrer: null,
            title: 'Landing',
            createdAt: new DateTimeImmutable('2026-07-09 10:00:00'),
        ),
        new PageViewEvent(
            id: 2,
            clientIds: AnalyticsEventFixture::clientIds('s-2', 'ip-b'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            referrer: null,
            title: 'Hello',
            createdAt: new DateTimeImmutable('2026-07-09 10:00:01'),
        ),
        new PageViewEvent(
            id: 3,
            clientIds: AnalyticsEventFixture::clientIds('s-3', 'ip-a'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::ProjectDetail, 7, 'https://example.com/projects/ledger'),
            referrer: null,
            title: 'Ledger',
            createdAt: new DateTimeImmutable('2026-07-09 10:00:02'),
        ),
        new TimeOnPageEvent(
            id: 4,
            clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::Landing, null, 'https://example.com/'),
            seconds: 10,
            createdAt: new DateTimeImmutable('2026-07-09 10:05:00'),
        ),
        new TimeOnPageEvent(
            id: 5,
            clientIds: AnalyticsEventFixture::clientIds('s-2', 'ip-b'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            seconds: 40,
            createdAt: new DateTimeImmutable('2026-07-09 10:05:01'),
        ),
        new TimeOnPageEvent(
            id: 6,
            clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            seconds: 80,
            createdAt: new DateTimeImmutable('2026-07-09 10:05:02'),
        ),
        new CallToActionClickEvent(
            id: 7,
            clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::Landing),
            target: CallToActionTarget::Cv,
            createdAt: new DateTimeImmutable('2026-07-09 10:06:00'),
        ),
        new LanguageSwitchEvent(
            id: 8,
            clientIds: AnalyticsEventFixture::clientIds('s-2', 'ip-b'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::Landing),
            locale: 'ru',
            createdAt: new DateTimeImmutable('2026-07-09 10:06:01'),
        ),
        new ThemeSwitchEvent(
            id: 9,
            clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::Landing),
            mode: 'dark',
            createdAt: new DateTimeImmutable('2026-07-09 10:06:02'),
        ),
        new ArticleReadEvent(
            id: 10,
            clientIds: AnalyticsEventFixture::clientIds('s-2', 'ip-b'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            articleId: 5,
            percentageCompleted: 50,
            createdAt: new DateTimeImmutable('2026-07-09 10:07:00'),
        ),
        new ArticleReadEvent(
            id: 11,
            clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            articleId: 5,
            percentageCompleted: 100,
            createdAt: new DateTimeImmutable('2026-07-09 10:07:01'),
        ),
        new ArticleReactionEvent(
            id: 12,
            clientIds: AnalyticsEventFixture::clientIds('s-2', 'ip-b'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            articleId: 5,
            reaction: SocialReaction::Like,
            createdAt: new DateTimeImmutable('2026-07-09 10:08:00'),
        ),
        new ArticleReactionEvent(
            id: 13,
            clientIds: AnalyticsEventFixture::clientIds('s-4', 'ip-d'),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            articleId: 5,
            reaction: SocialReaction::Dislike,
            createdAt: new DateTimeImmutable('2026-07-09 10:08:01'),
        ),
    ];
}

/**
 * @param list<\App\Features\Analytics\Domain\Entities\AnalyticsEvent> $events
 * @param list<DateTimeImmutable> $submissionDates
 */
function aggregateDaily(array $events, array $submissionDates = []): FakeAnalyticsDailyMetricRepository
{
    $metrics = new FakeAnalyticsDailyMetricRepository();
    $useCase = new AggregateDailyMetricsUseCase(
        new FakeAnalyticsEventRepository(periodEvents: $events),
        new FakeAnalyticsReportDataReader($submissionDates),
        $metrics,
    );

    $useCase->aggregate(new ReportPeriod(
        new DateTimeImmutable('2026-07-09 00:00:00'),
        new DateTimeImmutable('2026-07-09 23:59:59'),
    ));

    return $metrics;
}

/**
 * @param list<AnalyticsMetric> $rows
 */
function assertMetricRow(
    array $rows,
    MetricName $metric,
    ?string $dimension,
    float $value,
    int $samples,
    string $date = '2026-07-09',
): void {
    $matches = array_values(array_filter(
        $rows,
        static fn (AnalyticsMetric $row): bool => $row->toMetric() === $metric
            && $row->toDimension() === $dimension
            && $row->toDate()->format('Y-m-d') === $date,
    ));

    expect($matches)->toHaveCount(1);

    $row = $matches[0];

    expect($row->toValue())->toBe($value)
        ->and($row->toSamples())->toBe($samples);
}

describe('AggregateDailyMetricsUseCase', function (): void {
    it('computes the global metrics for the day', function (): void {
        $metrics = aggregateDaily(dayEvents(), [
            new DateTimeImmutable('2026-07-09 11:00:00'),
            new DateTimeImmutable('2026-07-09 12:00:00'),
        ]);

        $rows = $metrics->stored;

        assertMetricRow($rows, MetricName::PageViews, null, 3.0, 1);
        assertMetricRow($rows, MetricName::UniqueVisitors, null, 3.0, 1);
        assertMetricRow($rows, MetricName::FeedbackSubmissions, null, 2.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageSeconds, null, 130.0, 3);
        assertMetricRow($rows, MetricName::TimeOnPageSamples, null, 3.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageMin, null, 10.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageMax, null, 80.0, 1);
    });

    it('excludes events whose page url is an admin route (tracking is [Public]-only)', function (): void {
        $metrics = aggregateDaily([
            new PageViewEvent(
                id: 99,
                clientIds: AnalyticsEventFixture::clientIds('s-admin', 'ip-admin'),
                pageDetails: AnalyticsEventFixture::pageDetails(null, null, 'https://example.com/admin/analytics'),
                referrer: null,
                title: 'Analytics',
                createdAt: new DateTimeImmutable('2026-07-09 10:00:00'),
            ),
            new TimeOnPageEvent(
                id: 100,
                clientIds: AnalyticsEventFixture::clientIds('s-admin', 'ip-admin'),
                pageDetails: AnalyticsEventFixture::pageDetails(null, null, 'https://example.com/admin/analytics'),
                seconds: 500,
                createdAt: new DateTimeImmutable('2026-07-09 10:05:00'),
            ),
            ...dayEvents(),
        ]);

        $rows = $metrics->stored;

        // The admin page view + time-on-page are ignored: the global
        // metrics stay at the public-day values and no admin-URL page
        // row appears.
        assertMetricRow($rows, MetricName::PageViews, null, 3.0, 1);
        assertMetricRow($rows, MetricName::UniqueVisitors, null, 3.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageSeconds, null, 130.0, 3);
        expect(
            collect($rows)->contains(
                static fn (AnalyticsMetric $row): bool => $row->toDimension() === 'https://example.com/admin/analytics',
            ),
        )->toBeFalse();
    });

    it('computes the blog-scoped metrics from the events on article pages', function (): void {
        $metrics = aggregateDaily(dayEvents());

        $rows = $metrics->stored;

        assertMetricRow($rows, MetricName::BlogViews, null, 1.0, 1);
        // ip-a (view + time on page + read), ip-b (read + like) and ip-d (dislike).
        assertMetricRow($rows, MetricName::BlogUniqueVisitors, null, 3.0, 1);
        assertMetricRow($rows, MetricName::ReadTimeSeconds, null, 120.0, 2);
        assertMetricRow($rows, MetricName::ReadTimeSamples, null, 2.0, 1);
        assertMetricRow($rows, MetricName::ReadTimeMin, null, 40.0, 1);
        assertMetricRow($rows, MetricName::ReadTimeMax, null, 80.0, 1);
        assertMetricRow($rows, MetricName::CompletionRateSum, null, 150.0, 2);
        assertMetricRow($rows, MetricName::CompletionRateSamples, null, 2.0, 1);
        assertMetricRow($rows, MetricName::CompletionRateMin, null, 50.0, 1);
        assertMetricRow($rows, MetricName::CompletionRateMax, null, 100.0, 1);
        assertMetricRow($rows, MetricName::ReactionsLikes, null, 1.0, 1);
        assertMetricRow($rows, MetricName::ReactionsDislikes, null, 1.0, 1);
    });

    it('emits cta_clicks rows per target dimension', function (): void {
        $metrics = aggregateDaily(dayEvents());

        $rows = $metrics->stored;

        assertMetricRow($rows, MetricName::CtaClicks, 'cv', 1.0, 1);
        assertMetricRow($rows, MetricName::CtaClicks, 'language', 1.0, 1);
        assertMetricRow($rows, MetricName::CtaClicks, 'theme', 1.0, 1);
    });

    it('emits per-page time-on-page rows keyed by the page url', function (): void {
        $metrics = aggregateDaily(dayEvents());

        $rows = $metrics->stored;

        assertMetricRow($rows, MetricName::TimeOnPageSeconds, 'https://example.com/', 10.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageSamples, 'https://example.com/', 1.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageMin, 'https://example.com/', 10.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageMax, 'https://example.com/', 10.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageSeconds, 'https://example.com/blog/hello', 120.0, 2);
        assertMetricRow($rows, MetricName::TimeOnPageSamples, 'https://example.com/blog/hello', 2.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageMin, 'https://example.com/blog/hello', 40.0, 1);
        assertMetricRow($rows, MetricName::TimeOnPageMax, 'https://example.com/blog/hello', 80.0, 1);
    });

    it('emits the per-article rows keyed by article:{id}', function (): void {
        $metrics = aggregateDaily(dayEvents());

        $rows = $metrics->stored;

        assertMetricRow($rows, MetricName::Views, 'article:5', 1.0, 1);
        assertMetricRow($rows, MetricName::UniqueVisitors, 'article:5', 1.0, 1);
        assertMetricRow($rows, MetricName::ReadTimeSeconds, 'article:5', 120.0, 2);
        assertMetricRow($rows, MetricName::ReadTimeSamples, 'article:5', 2.0, 1);
        assertMetricRow($rows, MetricName::ReadTimeMin, 'article:5', 40.0, 1);
        assertMetricRow($rows, MetricName::ReadTimeMax, 'article:5', 80.0, 1);
        assertMetricRow($rows, MetricName::CompletionRateSum, 'article:5', 150.0, 2);
        assertMetricRow($rows, MetricName::CompletionRateSamples, 'article:5', 2.0, 1);
        assertMetricRow($rows, MetricName::CompletionRateMin, 'article:5', 50.0, 1);
        assertMetricRow($rows, MetricName::CompletionRateMax, 'article:5', 100.0, 1);
        assertMetricRow($rows, MetricName::ReactionsLikes, 'article:5', 1.0, 1);
        assertMetricRow($rows, MetricName::ReactionsDislikes, 'article:5', 1.0, 1);
    });

    it('emits the per-project rows keyed by project:{id}', function (): void {
        $metrics = aggregateDaily(dayEvents());

        $rows = $metrics->stored;

        assertMetricRow($rows, MetricName::Views, 'project:7', 1.0, 1);
        assertMetricRow($rows, MetricName::UniqueVisitors, 'project:7', 1.0, 1);
        // The project-scoped global uniques — the distinct IPs among
        // the events on project pages (ip-a here), not the sum of the
        // per-project uniques.
        assertMetricRow($rows, MetricName::ProjectUniqueVisitors, null, 1.0, 1);
    });

    it('counts one project unique visitor across several project pages (same visitor)', function (): void {
        $metrics = aggregateDaily([
            new PageViewEvent(
                id: 21,
                clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
                pageDetails: AnalyticsEventFixture::pageDetails(PageType::ProjectDetail, 1, 'https://example.com/projects/one'),
                referrer: null,
                title: 'One',
                createdAt: new DateTimeImmutable('2026-07-09 10:00:00'),
            ),
            new PageViewEvent(
                id: 22,
                clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
                pageDetails: AnalyticsEventFixture::pageDetails(PageType::ProjectDetail, 2, 'https://example.com/projects/two'),
                referrer: null,
                title: 'Two',
                createdAt: new DateTimeImmutable('2026-07-09 10:00:01'),
            ),
            new PageViewEvent(
                id: 23,
                clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
                pageDetails: AnalyticsEventFixture::pageDetails(PageType::ProjectDetail, 3, 'https://example.com/projects/three'),
                referrer: null,
                title: 'Three',
                createdAt: new DateTimeImmutable('2026-07-09 10:00:02'),
            ),
        ]);

        $rows = $metrics->stored;

        // Each project row carries its own unique count; the global
        // row deduplicates the same visitor across the project pages.
        assertMetricRow($rows, MetricName::Views, 'project:1', 1.0, 1);
        assertMetricRow($rows, MetricName::Views, 'project:2', 1.0, 1);
        assertMetricRow($rows, MetricName::Views, 'project:3', 1.0, 1);
        assertMetricRow($rows, MetricName::UniqueVisitors, 'project:1', 1.0, 1);
        assertMetricRow($rows, MetricName::UniqueVisitors, 'project:2', 1.0, 1);
        assertMetricRow($rows, MetricName::UniqueVisitors, 'project:3', 1.0, 1);
        assertMetricRow($rows, MetricName::ProjectUniqueVisitors, null, 1.0, 1);
    });

    it('aggregates every day of the range into its own rows', function (): void {
        $events = [
            ...dayEvents(),
            new PageViewEvent(
                id: 14,
                clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
                pageDetails: AnalyticsEventFixture::pageDetails(PageType::Landing),
                referrer: null,
                title: 'Landing',
                createdAt: new DateTimeImmutable('2026-07-10 10:00:00'),
            ),
        ];

        $metrics = new FakeAnalyticsDailyMetricRepository();
        $useCase = new AggregateDailyMetricsUseCase(
            new FakeAnalyticsEventRepository(periodEvents: $events),
            new FakeAnalyticsReportDataReader(),
            $metrics,
        );
        $useCase->aggregate(new ReportPeriod(
            new DateTimeImmutable('2026-07-09 00:00:00'),
            new DateTimeImmutable('2026-07-10 23:59:59'),
        ));

        expect(array_map(
            static fn (AnalyticsMetric $row): string => $row->toDate()->format('Y-m-d'),
            $metrics->stored,
        ))->toContain('2026-07-09')
            ->toContain('2026-07-10');

        assertMetricRow($metrics->stored, MetricName::PageViews, null, 3.0, 1, date: '2026-07-09');
        assertMetricRow($metrics->stored, MetricName::PageViews, null, 1.0, 1, date: '2026-07-10');
    });

    it('produces the identical row set when re-aggregating the same day', function (): void {
        $first = aggregateDaily(dayEvents());
        $second = aggregateDaily(dayEvents());

        $semantic = static fn (AnalyticsMetric $row): array => [
            $row->toMetric(),
            $row->toDate(),
            $row->toDimension(),
            $row->toValue(),
            $row->toSamples(),
        ];

        expect(array_map($semantic, $first->stored))->toEqual(array_map($semantic, $second->stored));
    });

    it('emits no rows when the day has no events and no feedback', function (): void {
        $metrics = aggregateDaily([]);

        expect($metrics->stored)->toBe([]);
    });

    it('defaults the range to the previous day plus the in-progress day', function (): void {
        $useCase = new AggregateDailyMetricsUseCase(
            new FakeAnalyticsEventRepository(),
            new FakeAnalyticsReportDataReader(),
            new FakeAnalyticsDailyMetricRepository(),
        );

        [$dateFrom, $dateTo] = $useCase->defaultDateRange();

        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');

        expect($dateFrom->format('Y-m-d'))->toBe($yesterday)
            ->and($dateFrom->format('H:i:s'))->toBe('00:00:00')
            ->and($dateTo->format('Y-m-d'))->toBe($today)
            ->and($dateTo->format('H:i:s'))->toBe('23:59:59');
    });
});
