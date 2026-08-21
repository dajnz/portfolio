<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use App\Features\Analytics\App\UseCases\GetDailyDigestUseCase;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Infra\Jobs\AggregateDailyMetricsJob;
use App\Features\Analytics\Infra\Jobs\RebuildReportsJob;
use App\Features\Analytics\Infra\Mail\SendAnalyticsReportMail;
use App\Features\Analytics\Infra\Models\AnalyticsEventModel;
use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

/**
 * The per-day metrics aggregation on real PostgreSQL (analytics.md:158-184,
 * B292): seeding `analytics_events` + `feedback` rows and running the
 * use case must produce exactly the catalog rows per day — the
 * weighted sum/samples pair, min/max, the per-dimension rows (CTA
 * targets, page URLs, `article:{id}`, `project:{id}`), the distinct-IP
 * visitors — with the upsert idempotence (re-running a day overwrites)
 * and the in-progress-day refresh; the job also dispatches the report
 * rebuild.
 */

function dailyMetricsPeriod(): ReportPeriod
{
    return new ReportPeriod(
        new DateTimeImmutable('2026-07-09 00:00:00'),
        new DateTimeImmutable('2026-07-09 23:59:59'),
    );
}

function dailyEvent(array $attributes = []): AnalyticsEventModel
{
    return AnalyticsEventModel::factory()->create([
        'created_at' => '2026-07-09 10:00:00',
        ...$attributes,
    ]);
}

/**
 * The shared fixture: page views (landing/article/project), time on
 * page (landing + article), CTA clicks (cv + language + theme),
 * article reads + reactions on the article page, and feedback rows on
 * the day. Returns the created article and project ids.
 *
 * @return array{article: int, project: int}
 */
function seedDailyMetricsFixture(): array
{
    $article = 5;
    $project = 7;

    dailyEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_type' => 'landing', 'created_at' => '2026-07-09 10:00:00']);
    dailyEvent(['event_type' => 'page_view', 'ip_address' => 'ip-b', 'page_type' => 'blog_article', 'page_entity_id' => $article, 'created_at' => '2026-07-09 10:00:01']);
    dailyEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_type' => 'project_detail', 'page_entity_id' => $project, 'created_at' => '2026-07-09 10:00:02']);
    dailyEvent(['event_type' => 'time_on_page', 'ip_address' => 'ip-a', 'page_type' => 'landing', 'page_url' => 'https://example.com/', 'metadata' => ['seconds' => 10], 'created_at' => '2026-07-09 10:05:00']);
    dailyEvent(['event_type' => 'time_on_page', 'ip_address' => 'ip-b', 'page_type' => 'blog_article', 'page_entity_id' => $article, 'metadata' => ['seconds' => 40], 'created_at' => '2026-07-09 10:05:01']);
    dailyEvent(['event_type' => 'time_on_page', 'ip_address' => 'ip-a', 'page_type' => 'blog_article', 'page_entity_id' => $article, 'metadata' => ['seconds' => 80], 'created_at' => '2026-07-09 10:05:02']);
    dailyEvent(['event_type' => 'cta_click_cv', 'ip_address' => 'ip-a', 'page_type' => 'landing', 'metadata' => null, 'created_at' => '2026-07-09 10:06:00']);
    dailyEvent(['event_type' => 'cta_click_language', 'ip_address' => 'ip-b', 'page_type' => 'landing', 'metadata' => ['locale' => 'ru'], 'created_at' => '2026-07-09 10:06:01']);
    dailyEvent(['event_type' => 'cta_click_theme', 'ip_address' => 'ip-a', 'page_type' => 'landing', 'metadata' => ['mode' => 'dark'], 'created_at' => '2026-07-09 10:06:02']);
    dailyEvent(['event_type' => 'article_read', 'ip_address' => 'ip-b', 'page_type' => 'blog_article', 'page_entity_id' => $article, 'metadata' => ['article_id' => $article, 'percentage_completed' => 50], 'created_at' => '2026-07-09 10:07:00']);
    dailyEvent(['event_type' => 'article_read', 'ip_address' => 'ip-a', 'page_type' => 'blog_article', 'page_entity_id' => $article, 'metadata' => ['article_id' => $article, 'percentage_completed' => 100], 'created_at' => '2026-07-09 10:07:01']);
    dailyEvent(['event_type' => 'article_reaction', 'ip_address' => 'ip-b', 'page_type' => 'blog_article', 'page_entity_id' => $article, 'metadata' => ['article_id' => $article, 'reaction' => 'like'], 'created_at' => '2026-07-09 10:08:00']);
    dailyEvent(['event_type' => 'article_reaction', 'ip_address' => 'ip-c', 'page_type' => 'blog_article', 'page_entity_id' => $article, 'metadata' => ['article_id' => $article, 'reaction' => 'dislike'], 'created_at' => '2026-07-09 10:08:01']);

    FeedbackModel::factory()->create(['created_at' => '2026-07-09 11:00:00']);
    FeedbackModel::factory()->create(['created_at' => '2026-07-09 12:00:00']);

    // Outside the day — must not contribute.
    dailyEvent(['event_type' => 'page_view', 'ip_address' => 'ip-x', 'page_type' => 'landing', 'created_at' => '2026-07-10 00:00:01']);
    FeedbackModel::factory()->create(['created_at' => '2026-07-10 00:00:01']);

    return ['article' => $article, 'project' => $project];
}

function runDailyAggregation(): void
{
    app(AggregateDailyMetricsUseCase::class)->aggregate(dailyMetricsPeriod());
}

function dailyMetricsRows(): array
{
    return app(AnalyticsDailyMetricRepositoryInterface::class)->rowsBetween(
        metric: null,
        dateFrom: new DateTimeImmutable('2026-07-09'),
        dateTo: new DateTimeImmutable('2026-07-09'),
    );
}

describe('AggregateDailyMetricsUseCase (integration)', function (): void {
    it('aggregates the full metric catalog for the day', function (): void {
        seedDailyMetricsFixture();
        runDailyAggregation();

        $rows = dailyMetricsRows();

        expect($rows)->not->toBeEmpty();

        $byMetric = [];
        foreach ($rows as $row) {
            $byMetric[$row->toMetric()->value][$row->toDimension() ?? ''] = [
                'value' => $row->toValue(),
                'samples' => $row->toSamples(),
            ];
        }

        // Global.
        expect($byMetric['page_views'][''])->toBe(['value' => 3.0, 'samples' => 1]);
        // ip-a, ip-b (page views + reads + like) and ip-c (dislike).
        expect($byMetric['unique_visitors'][''])->toBe(['value' => 3.0, 'samples' => 1]);
        expect($byMetric['feedback_submissions'][''])->toBe(['value' => 2.0, 'samples' => 1]);
        expect($byMetric['time_on_page_seconds'][''])->toBe(['value' => 130.0, 'samples' => 3]);
        expect($byMetric['time_on_page_samples'][''])->toBe(['value' => 3.0, 'samples' => 1]);
        expect($byMetric['time_on_page_min'][''])->toBe(['value' => 10.0, 'samples' => 1]);
        expect($byMetric['time_on_page_max'][''])->toBe(['value' => 80.0, 'samples' => 1]);

        // Blog-scoped.
        expect($byMetric['blog_views'][''])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['blog_unique_visitors'][''])->toBe(['value' => 3.0, 'samples' => 1]);
        expect($byMetric['read_time_seconds'][''])->toBe(['value' => 120.0, 'samples' => 2]);
        expect($byMetric['read_time_min'][''])->toBe(['value' => 40.0, 'samples' => 1]);
        expect($byMetric['read_time_max'][''])->toBe(['value' => 80.0, 'samples' => 1]);
        expect($byMetric['completion_rate_sum'][''])->toBe(['value' => 150.0, 'samples' => 2]);
        expect($byMetric['completion_rate_min'][''])->toBe(['value' => 50.0, 'samples' => 1]);
        expect($byMetric['completion_rate_max'][''])->toBe(['value' => 100.0, 'samples' => 1]);
        expect($byMetric['reactions_likes'][''])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['reactions_dislikes'][''])->toBe(['value' => 1.0, 'samples' => 1]);

        // CTA per target.
        expect($byMetric['cta_clicks']['cv'])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['cta_clicks']['language'])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['cta_clicks']['theme'])->toBe(['value' => 1.0, 'samples' => 1]);

        // Per page.
        expect($byMetric['time_on_page_seconds']['https://example.com/'])->toBe(['value' => 10.0, 'samples' => 1]);

        // Per article / per project.
        expect($byMetric['views']['article:5'])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['unique_visitors']['article:5'])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['read_time_seconds']['article:5'])->toBe(['value' => 120.0, 'samples' => 2]);
        expect($byMetric['completion_rate_sum']['article:5'])->toBe(['value' => 150.0, 'samples' => 2]);
        expect($byMetric['reactions_likes']['article:5'])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['reactions_dislikes']['article:5'])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['views']['project:7'])->toBe(['value' => 1.0, 'samples' => 1]);
        expect($byMetric['unique_visitors']['project:7'])->toBe(['value' => 1.0, 'samples' => 1]);
    });

    it('re-aggregating the same input produces the identical rows (upsert idempotence)', function (): void {
        seedDailyMetricsFixture();
        runDailyAggregation();

        $first = dailyMetricsRows();
        runDailyAggregation();
        $second = dailyMetricsRows();

        expect($second)->toHaveCount(count($first))
            ->and(array_map(
                static fn ($row): array => [
                    $row->toMetric(),
                    $row->toDimension(),
                    $row->toValue(),
                    $row->toSamples(),
                ],
                $second,
            ))->toBe(array_map(
                static fn ($row): array => [
                    $row->toMetric(),
                    $row->toDimension(),
                    $row->toValue(),
                    $row->toSamples(),
                ],
                $first,
            ));
    });

    it('refreshes the in-progress day when it is re-aggregated', function (): void {
        seedDailyMetricsFixture();
        runDailyAggregation();
        $first = dailyMetricsRows();

        dailyEvent(['event_type' => 'page_view', 'ip_address' => 'ip-e', 'page_type' => 'landing', 'created_at' => '2026-07-09 13:00:00']);
        runDailyAggregation();

        $second = dailyMetricsRows();

        // The row count stays flat — every row already existed — and
        // the overwritten rows carry the new values.
        $pageViews = array_values(array_filter(
            $second,
            static fn ($row): bool => $row->toMetric() === MetricName::PageViews && $row->toDimension() === null,
        ));
        $uniqueVisitors = array_values(array_filter(
            $second,
            static fn ($row): bool => $row->toMetric() === MetricName::UniqueVisitors && $row->toDimension() === null,
        ));

        expect($second)->toHaveCount(count($first))
            ->and($pageViews)->toHaveCount(1)
            ->and($pageViews[0]->toValue())->toBe(4.0)
            ->and($uniqueVisitors)->toHaveCount(1)
            ->and($uniqueVisitors[0]->toValue())->toBe(4.0);
    });
});

describe('AggregateDailyMetricsJob (integration)', function (): void {
    it('aggregates the range and dispatches the report rebuild', function (): void {
        Bus::fake();

        seedDailyMetricsFixture();

        $job = new AggregateDailyMetricsJob(
            new DateTimeImmutable('2026-07-09 00:00:00'),
            new DateTimeImmutable('2026-07-09 23:59:59'),
        );
        $job->handle(
            app(AggregateDailyMetricsUseCase::class),
            app(GetDailyDigestUseCase::class),
            app(SettingRepositoryInterface::class),
        );

        Bus::assertDispatched(RebuildReportsJob::class);

        $pageViews = array_values(array_filter(
            dailyMetricsRows(),
            static fn ($row): bool => $row->toMetric() === MetricName::PageViews && $row->toDimension() === null,
        ));

        expect($pageViews)->toHaveCount(1)
            ->and($pageViews[0]->toValue())->toBe(3.0);
    });

    it('queues the digest mail for the aggregated day when emailing is enabled', function (): void {
        seedDailyMetricsFixture();

        ArticleModel::factory()->create(['id' => 5]);
        ArticleTranslationModel::factory()->forLocale('en')->create([
            'article_id' => 5,
            'title' => 'Payments Gateway',
            'short_description' => 'A payments article.',
            'content' => 'The article body with enough words.',
        ]);

        app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailEnabled, true);
        app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailRecipient, 'admin@example.com');

        Mail::fake();

        $job = new AggregateDailyMetricsJob(
            new DateTimeImmutable('2026-07-09 00:00:00'),
            new DateTimeImmutable('2026-07-09 23:59:59'),
        );
        $job->handle(
            app(AggregateDailyMetricsUseCase::class),
            app(GetDailyDigestUseCase::class),
            app(SettingRepositoryInterface::class),
        );

        Mail::assertQueued(SendAnalyticsReportMail::class, function (SendAnalyticsReportMail $mail): bool {
            return $mail->to[0]['address'] === 'admin@example.com'
                && $mail->digest->day->format('Y-m-d') === '2026-07-09'
                && $mail->digest->pageViews === 3.0
                && $mail->digest->uniqueVisitors === 3.0
                && $mail->digest->feedbackSubmissions === 2.0
                && $mail->digest->topArticle?->title === 'Payments Gateway';
        });
    });

    it('does not send the digest mail when emailing is disabled', function (): void {
        app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailEnabled, false);
        app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailRecipient, 'admin@example.com');

        Mail::fake();

        $job = new AggregateDailyMetricsJob(
            new DateTimeImmutable('2026-07-09 00:00:00'),
            new DateTimeImmutable('2026-07-09 23:59:59'),
        );
        $job->handle(
            app(AggregateDailyMetricsUseCase::class),
            app(GetDailyDigestUseCase::class),
            app(SettingRepositoryInterface::class),
        );

        Mail::assertNotQueued(SendAnalyticsReportMail::class);
    });

    it('does not send the digest mail without a recipient', function (): void {
        app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailEnabled, true);

        Mail::fake();

        $job = new AggregateDailyMetricsJob(
            new DateTimeImmutable('2026-07-09 00:00:00'),
            new DateTimeImmutable('2026-07-09 23:59:59'),
        );
        $job->handle(
            app(AggregateDailyMetricsUseCase::class),
            app(GetDailyDigestUseCase::class),
            app(SettingRepositoryInterface::class),
        );

        Mail::assertNotQueued(SendAnalyticsReportMail::class);
    });
});
