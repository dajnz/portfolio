<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\ArticleReadEvent;
use App\Features\Analytics\Domain\Entities\ArticleReactionEvent;
use App\Features\Analytics\Domain\Entities\BlogReport;
use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Entities\TimeOnPageEvent;
use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Domain\Enums\SocialReaction;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use DateTimeImmutable;
use Tests\Support\AnalyticsEventFixture;

/**
 * The `blog` aggregation (analytics.md:177, B285/B287/B288/B289):
 * views on article pages (the page context from the frontend — B289),
 * blog-scoped unique visitors, the top-articles table (average read
 * time from `time_on_page` on the article page) and the reactions
 * series. The constructor IS the aggregation (B289).
 */

/**
 * Builds a typed event with the shared storage fields filled in.
 */
function blogEvent(
    string $class,
    array $args,
    ?string $ip = null,
    ?PageType $pageType = null,
    ?int $pageEntityId = null,
): mixed {
    return new $class(...array_merge(
        [
            'id' => 0,
            'clientIds' => AnalyticsEventFixture::clientIds(ip: $ip),
            'pageDetails' => AnalyticsEventFixture::pageDetails($pageType, $pageEntityId),
        ],
        $args,
        ['createdAt' => new DateTimeImmutable('2026-07-08 10:00:00')],
    ));
}

describe('BlogReport', function (): void {
    $period = new ReportPeriod(
        new DateTimeImmutable('2026-07-06 00:00:00'),
        new DateTimeImmutable('2026-07-12 23:59:59'),
    );
    $titles = [1 => 'Payments Gateway', 2 => 'Ledger App'];

    it('computes the typed results from the period events', function () use ($period, $titles): void {
        $events = [
            blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'A'], 'ip-a', PageType::BlogArticle, 1),
            blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'B'], 'ip-b', PageType::BlogArticle, 1),
            blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'C'], 'ip-c', PageType::ProjectDetail, 9),
            blogEvent(TimeOnPageEvent::class, ['seconds' => 40], 'ip-a', PageType::BlogArticle, 1),
            blogEvent(TimeOnPageEvent::class, ['seconds' => 80], 'ip-a', PageType::BlogArticle, 1),
            blogEvent(ArticleReadEvent::class, ['articleId' => 1, 'percentageCompleted' => 50], 'ip-b'),
            blogEvent(ArticleReadEvent::class, ['articleId' => 1, 'percentageCompleted' => 100], 'ip-c'),
            blogEvent(ArticleReactionEvent::class, ['articleId' => 1, 'reaction' => SocialReaction::Like], 'ip-a'),
            blogEvent(ArticleReactionEvent::class, ['articleId' => 1, 'reaction' => SocialReaction::Like], 'ip-b'),
            blogEvent(ArticleReactionEvent::class, ['articleId' => 1, 'reaction' => SocialReaction::Dislike], 'ip-d'),
        ];

        $report = new BlogReport($period, new DateTimeImmutable(), events: $events, articleTitles: $titles);

        expect($report->toScalar('total_views')->toValue())->toBe(2)
            ->and($report->toScalar('unique_visitors')->toValue())->toBe(4)
            ->and($report->toResults()['top_articles'])->toBeInstanceOf(ReportTableValue::class)
            ->and($report->toResults()['top_articles']->toRows())->toBe([[
                'id' => 1,
                'title' => 'Payments Gateway',
                'views' => 2,
                'avg_read_time' => 60.0,
                'completion_rate' => 75.0,
            ]])
            ->and($report->toResults()['reactions'])->toBeInstanceOf(ReportSeriesValue::class)
            ->and($report->toResults()['reactions']->toPoints())->toBe([
                ['label' => 'likes', 'value' => 2],
                ['label' => 'dislikes', 'value' => 1],
            ]);
    });

    it('orders the top articles by views descending with the id tiebreaker', function () use ($period, $titles): void {
        $events = [
            blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'A'], 'ip-a', PageType::BlogArticle, 2),
            blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'B'], 'ip-a', PageType::BlogArticle, 1),
        ];

        $report = new BlogReport($period, new DateTimeImmutable(), events: $events, articleTitles: $titles);

        expect($report->toResults()['top_articles']->toRows())->toBe([
            ['id' => 1, 'title' => 'Payments Gateway', 'views' => 1, 'avg_read_time' => null, 'completion_rate' => null],
            ['id' => 2, 'title' => 'Ledger App', 'views' => 1, 'avg_read_time' => null, 'completion_rate' => null],
        ]);
    });

    it('counts only distinct blog-scoped visitor ips', function () use ($period, $titles): void {
        $events = [
            blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'A'], 'ip-a', PageType::BlogArticle, 1),
            blogEvent(TimeOnPageEvent::class, ['seconds' => 10], 'ip-a', PageType::BlogArticle, 1),
            blogEvent(ArticleReadEvent::class, ['articleId' => 1, 'percentageCompleted' => 60], 'ip-a'),
            blogEvent(ArticleReadEvent::class, ['articleId' => 1, 'percentageCompleted' => 70], 'ip-b'),
            blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'X'], 'ip-c', PageType::ProjectDetail, 9),
        ];

        $report = new BlogReport($period, new DateTimeImmutable(), events: $events, articleTitles: $titles);

        expect($report->toScalar('unique_visitors')->toValue())->toBe(2);
    });

    it('renders empty results without blog views', function () use ($period, $titles): void {
        $report = new BlogReport($period, new DateTimeImmutable(), events: [], articleTitles: $titles);

        expect($report->toScalar('total_views')->toValue())->toBe(0)
            ->and($report->toResults()['top_articles']->toRows())->toBe([])
            ->and($report->toResults()['reactions']->toPoints())->toBe([
                ['label' => 'likes', 'value' => 0],
                ['label' => 'dislikes', 'value' => 0],
            ]);
    });

    it('carries the previous-period values into the scalars', function () use ($period, $titles): void {
        $previous = new BlogReport(
            $period,
            new DateTimeImmutable(),
            events: [blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'A'], 'ip-a', PageType::BlogArticle, 1)],
            articleTitles: $titles,
        );
        $events = array_fill(
            0,
            2,
            blogEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'B'], 'ip-a', PageType::BlogArticle, 1),
        );

        $report = new BlogReport($period, new DateTimeImmutable(), events: $events, articleTitles: $titles, previous: $previous);

        expect($report->toScalar('total_views')->toPrevious())->toBe(1)
            ->and($report->toScalar('total_views')->toValue())->toBe(2);
    });

    it('serializes the results and restores them from the payload', function () use ($period, $titles): void {
        $report = new BlogReport($period, new DateTimeImmutable(), events: [], articleTitles: $titles);

        $restored = BlogReport::fromPayload(
            7,
            $period,
            array_map(static fn ($result): array => $result->toArray(), $report->toResults()),
            $report->toGeneratedAt(),
        );

        expect($restored->toId())->toBe(7)
            ->and($restored->toScalar('total_views')->toValue())->toBe(0);
    });
});
