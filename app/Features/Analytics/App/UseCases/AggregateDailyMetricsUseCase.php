<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\Entities\AnalyticsEvent;
use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Entities\ArticleReadEvent;
use App\Features\Analytics\Domain\Entities\ArticleReactionEvent;
use App\Features\Analytics\Domain\Entities\CallToActionClickEvent;
use App\Features\Analytics\Domain\Entities\LanguageSwitchEvent;
use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Entities\ThemeSwitchEvent;
use App\Features\Analytics\Domain\Entities\TimeOnPageEvent;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\SocialReaction;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * The per-day metrics aggregation (analytics.md:158-180, B292): the
 * 11.1 event-aggregation logic reworked per day over a date range.
 * For every day of the range it computes the full metric catalog from
 * that day's events (typed entities via `eventsBetween`) and the
 * feedback submissions (the cross-feature reader), and upserts the
 * rows via the daily-metric repository — re-running a day overwrites
 * (idempotent). The catalog per analytics.md:172-178: global rows
 * (dimension NULL), `cta_clicks` per target, per-page time on page by
 * URL, per-article and per-project rows keyed as `article:{id}` /
 * `project:{id}`. `unique_visitors` is always `COUNT(DISTINCT
 * ip_hash)` (session ids are client-generated and untrustworthy);
 * blog-scoped uniques count distinct IPs among the events on article
 * pages (`toBlogArticleId() !== null`), project-scoped uniques among
 * the events on project pages (`toProjectId() !== null`) — the same
 * visitor across several project pages counts once; per-entity uniques
 * count the page views' distinct IPs on that entity's pages.
 */
final class AggregateDailyMetricsUseCase
{
    public function __construct(
        private readonly AnalyticsEventRepositoryInterface $events,
        private readonly AnalyticsReportDataReaderInterface $dataReader,
        private readonly AnalyticsDailyMetricRepositoryInterface $metrics,
    ) {
    }

    public function aggregate(ReportPeriod $period): void
    {
        $events = $this->events->eventsBetween($period);
        $submissionDates = $this->dataReader->feedbackSubmissions($period);

        $rows = [];

        foreach ($this->daysIn($period) as $day) {
            $dayKey = $day->format('Y-m-d');
            $dayEvents = array_values(array_filter(
                $events,
                static fn (AnalyticsEvent $event): bool => $event->toCreatedAt()->format('Y-m-d') === $dayKey,
            ));
            $feedbackCount = count(array_filter(
                $submissionDates,
                static fn (DateTimeImmutable $date): bool => $date->format('Y-m-d') === $dayKey,
            ));

            $rows = [...$rows, ...$this->rowsForDay($day, $dayEvents, $feedbackCount)];
        }

        $this->metrics->upsertBatch($rows);
    }

    /**
     * The default aggregation range (analytics.md:182-184): the
     * previous day + the in-progress day, in the server-local
     * timezone — the dashboard stays live. Shared by the scheduled
     * command and the job.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public function defaultDateRange(): array
    {
        $today = new DateTimeImmutable('today');

        return [
            $today->modify('-1 day'),
            $today->modify('+1 day')->modify('-1 second'),
        ];
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function daysIn(ReportPeriod $period): array
    {
        $days = [];

        for ($day = $period->toPeriodStart(); $day <= $period->toPeriodEnd(); $day = $day->modify('+1 day')) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * @param list<AnalyticsEvent> $events
     *
     * @return list<AnalyticsMetric>
     */
    private function rowsForDay(DateTimeImmutable $day, array $events, int $feedbackCount): array
    {
        $totals = $this->accumulate($events);

        return [
            ...$this->globalRows($day, $totals, $feedbackCount),
            ...$this->ctaRows($day, $totals['cta']),
            ...$this->pageRows($day, $totals['pages']),
            ...$this->articleRows($day, $totals['articles']),
            ...$this->projectRows($day, $totals['projects']),
        ];
    }

    /**
     * Whether the event's page URL points into the admin section
     * (analytics.md:3,38 — tracking is `[Public]`-only): such events
     * are ignored at aggregation. Null page URLs (events without one)
     * are never treated as admin.
     */
    private function isAdminPageUrl(?string $pageUrl): bool
    {
        if ($pageUrl === null) {
            return false;
        }

        $path = parse_url($pageUrl, PHP_URL_PATH);

        return $path !== null && str_starts_with($path, '/admin');
    }

    /**
     * The single-pass accumulation of the day's events (the 11.1
     * `OverallReport`/`BlogReport`/`ProjectsReport::accumulate()`
     * logic combined — B292). CTA targets come from the event types
     * (B287): `CallToActionClickEvent`→its target,
     * `LanguageSwitchEvent`→`language`, `ThemeSwitchEvent`→`theme`.
     *
     * @param list<AnalyticsEvent> $events
     *
     * @return array{
     *     page_views: int,
     *     visitors: array<string, true>,
     *     blog_visitors: array<string, true>,
     *     project_visitors: array<string, true>,
     *     seconds: list<int>,
     *     article_seconds: list<int>,
     *     completion: list<int>,
     *     likes: int,
     *     dislikes: int,
     *     cta: array<string, int>,
     *     pages: array<string, list<int>>,
     *     articles: array<int, array{
     *         views: int,
     *         visitors: array<string, true>,
     *         read_seconds: list<int>,
     *         completion: list<int>,
     *         likes: int,
     *         dislikes: int,
     *     }>,
     *     projects: array<int, array{views: int, visitors: array<string, true>}>,
     * }
     */
    private function accumulate(array $events): array
    {
        $totals = [
            'page_views' => 0,
            'visitors' => [],
            'blog_visitors' => [],
            'project_visitors' => [],
            'seconds' => [],
            'article_seconds' => [],
            'completion' => [],
            'likes' => 0,
            'dislikes' => 0,
            'cta' => [],
            'pages' => [],
            'articles' => [],
            'projects' => [],
        ];

        foreach ($events as $event) {
            // The aggregation net (analytics.md:3,38 — tracking is
            // `[Public]`-only): events whose page URL points into the
            // admin section are ignored, so admin browsing can never
            // pollute the global metrics even if the client-side guard
            // regresses. `page_type: null` events on PUBLIC urls
            // (legacy rows without the page context, B289) are kept.
            if ($this->isAdminPageUrl($event->toPageUrl())) {
                continue;
            }

            $this->accumulateEvent($totals, $event);
        }

        return $totals;
    }

    /**
     * The per-event dispatch: the shared client/entity context feeds
     * the distinct-IP sets, then the event type routes to its
     * accumulator.
     *
     * @param array<string, mixed> $totals
     */
    private function accumulateEvent(array &$totals, AnalyticsEvent $event): void
    {
        $this->trackVisitorSets($totals, $event);

        if ($event instanceof PageViewEvent) {
            $this->accumulatePageView($totals, $event);
        } elseif ($event instanceof TimeOnPageEvent) {
            $this->accumulateTimeOnPage($totals, $event);
        } elseif ($event instanceof CallToActionClickEvent) {
            $this->incrementCta($totals, $event->toTarget()->value);
        } elseif ($event instanceof LanguageSwitchEvent) {
            $this->incrementCta($totals, 'language');
        } elseif ($event instanceof ThemeSwitchEvent) {
            $this->incrementCta($totals, 'theme');
        } elseif ($event instanceof ArticleReadEvent) {
            $this->accumulateArticleRead($totals, $event);
        } elseif ($event instanceof ArticleReactionEvent) {
            $this->accumulateArticleReaction($totals, $event);
        }
    }

    /**
     * The distinct-IP sets: the global visitors (every event), the
     * blog-scoped (events on article pages) and the project-scoped
     * (events on project pages).
     *
     * @param array<string, mixed> $totals
     */
    private function trackVisitorSets(array &$totals, AnalyticsEvent $event): void
    {
        $ip = $event->toIpAddress();
        $articleId = $event->toPageDetails()->toBlogArticleId();
        $projectId = $event->toPageDetails()->toProjectId();

        if ($ip !== null) {
            $totals['visitors'][$ip] = true;
        }

        if ($articleId !== null && $ip !== null) {
            $totals['blog_visitors'][$ip] = true;
        }

        if ($projectId !== null && $ip !== null) {
            $totals['project_visitors'][$ip] = true;
        }
    }

    /**
     * @param array<string, mixed> $totals
     */
    private function accumulatePageView(array &$totals, PageViewEvent $event): void
    {
        $totals['page_views']++;

        $articleId = $event->toPageDetails()->toBlogArticleId();
        $projectId = $event->toPageDetails()->toProjectId();
        $ip = $event->toIpAddress();

        if ($articleId !== null) {
            $totals['articles'][$articleId]['views'] = ($totals['articles'][$articleId]['views'] ?? 0) + 1;

            if ($ip !== null) {
                $totals['articles'][$articleId]['visitors'][$ip] = true;
            }
        }

        if ($projectId !== null) {
            $totals['projects'][$projectId]['views'] = ($totals['projects'][$projectId]['views'] ?? 0) + 1;

            if ($ip !== null) {
                $totals['projects'][$projectId]['visitors'][$ip] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $totals
     */
    private function accumulateTimeOnPage(array &$totals, TimeOnPageEvent $event): void
    {
        $totals['seconds'][] = $event->toSeconds();

        $url = $event->toPageUrl();

        if ($url !== null) {
            $totals['pages'][$url][] = $event->toSeconds();
        }

        $articleId = $event->toPageDetails()->toBlogArticleId();

        if ($articleId !== null) {
            $totals['article_seconds'][] = $event->toSeconds();
            $totals['articles'][$articleId]['read_seconds'][] = $event->toSeconds();
        }
    }

    /**
     * @param array<string, mixed> $totals
     */
    private function incrementCta(array &$totals, string $target): void
    {
        $totals['cta'][$target] = ($totals['cta'][$target] ?? 0) + 1;
    }

    /**
     * @param array<string, mixed> $totals
     */
    private function accumulateArticleRead(array &$totals, ArticleReadEvent $event): void
    {
        $percentage = $event->toPercentageCompleted();
        $totals['completion'][] = $percentage;
        $totals['articles'][$event->toArticleId()]['completion'][] = $percentage;
    }

    /**
     * @param array<string, mixed> $totals
     */
    private function accumulateArticleReaction(array &$totals, ArticleReactionEvent $event): void
    {
        $articleId = $event->toArticleId();

        if ($event->toReaction() === SocialReaction::Like) {
            $totals['likes']++;
            $totals['articles'][$articleId]['likes'] = ($totals['articles'][$articleId]['likes'] ?? 0) + 1;

            return;
        }

        $totals['dislikes']++;
        $totals['articles'][$articleId]['dislikes'] = ($totals['articles'][$articleId]['dislikes'] ?? 0) + 1;
    }

    /**
     * The dimension-NULL rows (analytics.md:174): the page-view count,
     * the distinct-IP visitors across ALL events, the feedback
     * submissions, the time-on-page sum/samples/min/max, the blog
     * views + blog-scoped uniques, the blog read time (time-on-page on
     * article pages), the completion rate and the reactions.
     *
     * @param array{
     *     page_views: int,
     *     visitors: array<string, true>,
     *     blog_visitors: array<string, true>,
     *     project_visitors: array<string, true>,
     *     seconds: list<int>,
     *     article_seconds: list<int>,
     *     completion: list<int>,
     *     likes: int,
     *     dislikes: int,
     *     cta: array<string, int>,
     *     pages: array<string, list<int>>,
     *     articles: array<int, array<string, mixed>>,
     *     projects: array<int, array{views: int, visitors: array<string, true>}>,
     * } $totals
     *
     * @return list<AnalyticsMetric>
     */
    private function globalRows(DateTimeImmutable $day, array $totals, int $feedbackCount): array
    {
        return $this->withoutNulls([
            $this->countRow($day, MetricName::PageViews, null, $totals['page_views']),
            $this->countRow($day, MetricName::UniqueVisitors, null, count($totals['visitors'])),
            $this->countRow($day, MetricName::FeedbackSubmissions, null, $feedbackCount),
            $this->sumRow($day, MetricName::TimeOnPageSeconds, null, $totals['seconds']),
            $this->countRow($day, MetricName::TimeOnPageSamples, null, count($totals['seconds'])),
            ...$this->minMaxRows($day, MetricName::TimeOnPageMin, MetricName::TimeOnPageMax, null, $totals['seconds']),
            $this->countRow($day, MetricName::BlogViews, null, $this->blogViews($totals['articles'])),
            $this->countRow($day, MetricName::BlogUniqueVisitors, null, $this->blogVisitors($totals['blog_visitors'])),
            $this->countRow($day, MetricName::ProjectUniqueVisitors, null, $this->projectVisitors($totals['project_visitors'])),
            $this->sumRow($day, MetricName::ReadTimeSeconds, null, $totals['article_seconds']),
            $this->countRow($day, MetricName::ReadTimeSamples, null, count($totals['article_seconds'])),
            ...$this->minMaxRows($day, MetricName::ReadTimeMin, MetricName::ReadTimeMax, null, $totals['article_seconds']),
            $this->sumRow($day, MetricName::CompletionRateSum, null, $totals['completion']),
            $this->countRow($day, MetricName::CompletionRateSamples, null, count($totals['completion'])),
            ...$this->minMaxRows($day, MetricName::CompletionRateMin, MetricName::CompletionRateMax, null, $totals['completion']),
            $this->countRow($day, MetricName::ReactionsLikes, null, $totals['likes']),
            $this->countRow($day, MetricName::ReactionsDislikes, null, $totals['dislikes']),
        ]);
    }

    /**
     * The blog page views — the page-view events with an article page
     * context (`toBlogArticleId() !== null`): the sum of the
     * per-article views.
     *
     * @param array<int, array<string, mixed>> $articles
     */
    private function blogViews(array $articles): int
    {
        return array_sum(array_map(
            static fn (array $article): int => $article['views'] ?? 0,
            $articles,
        ));
    }

    /**
     * The blog-scoped unique visitors — the distinct IPs among ALL
     * events on article pages (`toBlogArticleId() !== null`,
     * analytics.md:180).
     *
     * @param array<string, true> $blogVisitors
     */
    private function blogVisitors(array $blogVisitors): int
    {
        return count($blogVisitors);
    }

    /**
     * The project-scoped unique visitors — the distinct IPs among ALL
     * events on project pages (`toProjectId() !== null`): the same
     * visitor across several project pages counts once (the per-project
     * `unique_visitors` rows stay per-project).
     *
     * @param array<string, true> $projectVisitors
     */
    private function projectVisitors(array $projectVisitors): int
    {
        return count($projectVisitors);
    }

    /**
     * The `cta_clicks` rows (analytics.md:175): one per target that
     * received clicks — `github`/`demo`/`cv`/`feedback` from the plain
     * CTA events, `language`/`theme` from the switch events.
     *
     * @param array<string, int> $cta
     *
     * @return list<AnalyticsMetric>
     */
    private function ctaRows(DateTimeImmutable $day, array $cta): array
    {
        $rows = [];

        foreach ($cta as $target => $count) {
            $row = $this->countRow($day, MetricName::CtaClicks, $target, $count);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * The per-page time-on-page rows (analytics.md:176) — dimension =
     * the page URL.
     *
     * @param array<string, list<int>> $pages
     *
     * @return list<AnalyticsMetric>
     */
    private function pageRows(DateTimeImmutable $day, array $pages): array
    {
        $rows = [];

        foreach ($pages as $url => $seconds) {
            $rows = [
                ...$rows,
                $this->sumRow($day, MetricName::TimeOnPageSeconds, $url, $seconds),
                $this->countRow($day, MetricName::TimeOnPageSamples, $url, count($seconds)),
                ...$this->minMaxRows($day, MetricName::TimeOnPageMin, MetricName::TimeOnPageMax, $url, $seconds),
            ];
        }

        return $this->withoutNulls($rows);
    }

    /**
     * The per-article rows (analytics.md:177) — dimension
     * `article:{id}`: views + the page views' distinct IPs, the read
     * time from time-on-page events on the article page, the
     * completion rate from `article_read` percentages, the reactions.
     *
     * @param array<int, array<string, mixed>> $articles
     *
     * @return list<AnalyticsMetric>
     */
    private function articleRows(DateTimeImmutable $day, array $articles): array
    {
        $rows = [];

        foreach ($articles as $articleId => $article) {
            $dimension = 'article:'.$articleId;
            $views = $article['views'] ?? 0;
            $readSeconds = $article['read_seconds'] ?? [];
            $completion = $article['completion'] ?? [];

            $rows = [
                ...$rows,
                $this->countRow($day, MetricName::Views, $dimension, $views),
                $this->countRow($day, MetricName::UniqueVisitors, $dimension, count($article['visitors'] ?? [])),
                $this->sumRow($day, MetricName::ReadTimeSeconds, $dimension, $readSeconds),
                $this->countRow($day, MetricName::ReadTimeSamples, $dimension, count($readSeconds)),
                ...$this->minMaxRows($day, MetricName::ReadTimeMin, MetricName::ReadTimeMax, $dimension, $readSeconds),
                $this->sumRow($day, MetricName::CompletionRateSum, $dimension, $completion),
                $this->countRow($day, MetricName::CompletionRateSamples, $dimension, count($completion)),
                ...$this->minMaxRows($day, MetricName::CompletionRateMin, MetricName::CompletionRateMax, $dimension, $completion),
                $this->countRow($day, MetricName::ReactionsLikes, $dimension, $article['likes'] ?? 0),
                $this->countRow($day, MetricName::ReactionsDislikes, $dimension, $article['dislikes'] ?? 0),
            ];
        }

        return $this->withoutNulls($rows);
    }

    /**
     * The per-project rows (analytics.md:178) — dimension
     * `project:{id}`: views + the page views' distinct IPs.
     *
     * @param array<int, array{views: int, visitors: array<string, true>}> $projects
     *
     * @return list<AnalyticsMetric>
     */
    private function projectRows(DateTimeImmutable $day, array $projects): array
    {
        $rows = [];

        foreach ($projects as $projectId => $project) {
            $dimension = 'project:'.$projectId;

            $rows = [
                ...$rows,
                $this->countRow($day, MetricName::Views, $dimension, $project['views']),
                $this->countRow($day, MetricName::UniqueVisitors, $dimension, count($project['visitors'])),
            ];
        }

        return $this->withoutNulls($rows);
    }

    /**
     * A count-like metric row: value = the count, samples = 1. Null
     * for a zero count — the catalog stores only what happened.
     */
    private function countRow(
        DateTimeImmutable $day,
        MetricName $metric,
        ?string $dimension,
        int $count,
    ): ?AnalyticsMetric {
        if ($count < 1) {
            return null;
        }

        return $this->metricRow($day, $metric, $dimension, (float) $count, 1);
    }

    /**
     * A sum-like metric row (analytics.md:170): value = the sum,
     * samples = the number of samples — the weighted aggregation at
     * the report layer stays exact (`SUM(value)/SUM(samples)`).
     *
     * @param list<int> $values
     */
    private function sumRow(
        DateTimeImmutable $day,
        MetricName $metric,
        ?string $dimension,
        array $values,
    ): ?AnalyticsMetric {
        if ($values === []) {
            return null;
        }

        return $this->metricRow($day, $metric, $dimension, (float) array_sum($values), count($values));
    }

    /**
     * The min/max row pair (analytics.md:170) — plain values, samples
     * = 1. Both null when there are no samples.
     *
     * @param list<int> $values
     *
     * @return array{0: ?AnalyticsMetric, 1: ?AnalyticsMetric}
     */
    private function minMaxRows(
        DateTimeImmutable $day,
        MetricName $minMetric,
        MetricName $maxMetric,
        ?string $dimension,
        array $values,
    ): array {
        if ($values === []) {
            return [null, null];
        }

        return [
            $this->metricRow($day, $minMetric, $dimension, (float) min($values), 1),
            $this->metricRow($day, $maxMetric, $dimension, (float) max($values), 1),
        ];
    }

    private function metricRow(
        DateTimeImmutable $day,
        MetricName $metric,
        ?string $dimension,
        float $value,
        int $samples,
    ): AnalyticsMetric {
        return new AnalyticsMetric(
            id: 0,
            metric: $metric,
            date: $day,
            dimension: $dimension,
            value: $value,
            samples: $samples,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param list<?AnalyticsMetric> $rows
     *
     * @return list<AnalyticsMetric>
     */
    private function withoutNulls(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            static fn (?AnalyticsMetric $row): bool => $row !== null,
        ));
    }
}
