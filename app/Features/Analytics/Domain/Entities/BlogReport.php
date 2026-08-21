<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\Enums\SocialReaction;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use DateTimeImmutable;

/**
 * The `blog` report (analytics.md:177, B285/B287/B288/B289): the
 * scalar results (period views on blog article pages, blog-scoped
 * unique visitors — each with the previous-period value), the
 * most-viewed articles as a table and the like/dislike summary as a
 * series. The article attribution comes from the events' page context
 * (`toBlogArticleId()` — B289), the EN titles from the cross-feature
 * reader. The per-article average read time comes from `time_on_page`
 * events on the article page (the frontend sends no seconds on
 * `article_read` — B287); the completion rate comes from `article_read`
 * percentage payloads.
 */
final class BlogReport extends AnalyticsReport
{
    public const REPORT_TYPE = 'blog';

    /**
     * The constructor IS the aggregation (B289): with `$events` given,
     * the results are computed from the period's events; without them,
     * `$results` must hold the stored results (the persisted-form path
     * used by the factory).
     *
     * @param list<AnalyticsEvent>|null $events
     * @param array<int, string> $articleTitles
     */
    public function __construct(
        ReportPeriod $period,
        DateTimeImmutable $generatedAt,
        array $results = [],
        ?array $events = null,
        array $articleTitles = [],
        ?AnalyticsReport $previous = null,
        int $id = 0,
    ) {
        if ($events !== null) {
            $results = $this->aggregateFrom($events, $articleTitles, $previous);
        }

        parent::__construct($id, $period, $results, $generatedAt);
    }

    /**
     * The persisted-form constructor — the one needed static (B289).
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(
        int $id,
        ReportPeriod $period,
        array $payload,
        DateTimeImmutable $generatedAt,
    ): self {
        return new self(
            $period,
            $generatedAt,
            [
                'total_views' => ReportScalarValue::fromArray($payload['total_views']),
                'unique_visitors' => ReportScalarValue::fromArray($payload['unique_visitors']),
                'top_articles' => ReportTableValue::fromArray($payload['top_articles']),
                'reactions' => ReportSeriesValue::fromArray($payload['reactions']),
            ],
            id: $id,
        );
    }

    /**
     * @param list<AnalyticsEvent> $events
     * @param array<int, string> $articleTitles
     *
     * @return array<string, ReportScalarValue|ReportSeriesValue|ReportTableValue>
     */
    private function aggregateFrom(array $events, array $articleTitles, ?AnalyticsReport $previous): array
    {
        $accumulated = $this->accumulate($events);

        return [
            'total_views' => new ReportScalarValue(
                $accumulated['views_total'],
                $this->previousScalarValue($previous, 'total_views'),
            ),
            'unique_visitors' => new ReportScalarValue(
                count($accumulated['visitors']),
                $this->previousScalarValue($previous, 'unique_visitors'),
            ),
            'top_articles' => $this->topArticlesTable(
                $accumulated['views_by_article'],
                $accumulated['read_seconds_by_article'],
                $accumulated['completion_by_article'],
                $articleTitles,
            ),
            'reactions' => $this->reactionsSeries($accumulated['reactions']),
        ];
    }

    /**
     * @param list<AnalyticsEvent> $events
     *
     * @return array{
     *     views_total: int,
     *     visitors: array<string, true>,
     *     views_by_article: array<int, int>,
     *     read_seconds_by_article: array<int, list<int>>,
     *     completion_by_article: array<int, list<int>>,
     *     reactions: array<string, int>,
     * }
     */
    private function accumulate(array $events): array
    {
        $viewsTotal = 0;
        $visitors = [];
        $viewsByArticle = [];
        $readSecondsByArticle = [];
        $completionByArticle = [];
        $reactions = ['likes' => 0, 'dislikes' => 0];

        foreach ($events as $event) {
            $articleId = $this->articleIdFor($event);

            if ($articleId !== null && $event->toIpAddress() !== null) {
                $visitors[$event->toIpAddress()] = true;
            }

            if ($event instanceof PageViewEvent && $articleId !== null) {
                $viewsTotal++;
                $viewsByArticle[$articleId] = ($viewsByArticle[$articleId] ?? 0) + 1;
            } elseif ($event instanceof TimeOnPageEvent && $articleId !== null) {
                $readSecondsByArticle[$articleId][] = $event->toSeconds();
            } elseif ($event instanceof ArticleReadEvent) {
                $completionByArticle[$event->toArticleId()][] = $event->toPercentageCompleted();

                if ($event->toIpAddress() !== null) {
                    $visitors[$event->toIpAddress()] = true;
                }
            } elseif ($event instanceof ArticleReactionEvent) {
                $this->accumulateReaction($event, $reactions);

                if ($event->toIpAddress() !== null) {
                    $visitors[$event->toIpAddress()] = true;
                }
            }
        }

        return [
            'views_total' => $viewsTotal,
            'visitors' => $visitors,
            'views_by_article' => $viewsByArticle,
            'read_seconds_by_article' => $readSecondsByArticle,
            'completion_by_article' => $completionByArticle,
            'reactions' => $reactions,
        ];
    }

    /**
     * The article id for URL-agnostic events (page views + time on
     * page on the article page) — null for unrelated events/pages. The
     * page context comes from the frontend (B289), no URL lookup.
     */
    private function articleIdFor(AnalyticsEvent $event): ?int
    {
        if (! $event instanceof PageViewEvent && ! $event instanceof TimeOnPageEvent) {
            return null;
        }

        return $event->toPageDetails()->toBlogArticleId();
    }

    /**
     * @param array<string, int> $reactions
     */
    private function accumulateReaction(ArticleReactionEvent $event, array &$reactions): void
    {
        // The enum values are `like`/`dislike` — the result labels are
        // `likes`/`dislikes` (analytics.md:177).
        $reactions[match ($event->toReaction()) {
            SocialReaction::Like => 'likes',
            SocialReaction::Dislike => 'dislikes',
        }]++;
    }

    /**
     * @param array<int, int> $viewsByArticle
     * @param array<int, list<int>> $readSecondsByArticle
     * @param array<int, list<int>> $completionByArticle
     * @param array<int, string> $titles
     */
    private function topArticlesTable(
        array $viewsByArticle,
        array $readSecondsByArticle,
        array $completionByArticle,
        array $titles,
    ): ReportTableValue {
        $rows = [];

        foreach ($viewsByArticle as $articleId => $views) {
            $rows[] = [
                'id' => $articleId,
                'title' => $titles[$articleId] ?? '',
                'views' => $views,
                'avg_read_time' => $this->averageOf($readSecondsByArticle[$articleId] ?? []),
                'completion_rate' => $this->averageOf($completionByArticle[$articleId] ?? []),
            ];
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => $b['views'] <=> $a['views'] ?: $a['id'] <=> $b['id'],
        );

        return new ReportTableValue(
            ['id', 'title', 'views', 'avg_read_time', 'completion_rate'],
            $rows,
        );
    }

    /**
     * @param list<int> $samples
     */
    private function averageOf(array $samples): ?float
    {
        return $this->average(array_sum($samples), count($samples));
    }

    /**
     * @param array{likes: int, dislikes: int} $reactions
     */
    private function reactionsSeries(array $reactions): ReportSeriesValue
    {
        return new ReportSeriesValue([
            ['label' => 'likes', 'value' => $reactions['likes']],
            ['label' => 'dislikes', 'value' => $reactions['dislikes']],
        ]);
    }
}
