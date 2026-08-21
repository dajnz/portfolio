<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Infra\Jobs\AggregateAnalyticsReportsJob;
use App\Features\Analytics\Infra\Mail\SendAnalyticsReportMail;
use App\Features\Analytics\Infra\Models\AnalyticsEventModel;
use App\Features\Analytics\Infra\Models\AnalyticsReportModel;
use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Database\Seeders\SettingsSeeder;
use DateTimeImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

/**
 * Creates an analytics event at an explicit timestamp (period tests
 * seed events on both sides of the boundary).
 */
function analyticsEvent(array $attributes = []): AnalyticsEventModel
{
    return AnalyticsEventModel::factory()->create($attributes);
}

/**
 * The shared daily-generation fixture: the completed-period events
 * (2026-07-09, page context per B289 — the payloads mirror the real
 * frontend: article_read carries no seconds (B287), the CTA clicks no
 * target metadata), the article/project with EN titles, the feedback
 * submissions on both sides of the boundary, and one excluded
 * current-period event. Returns the created article and project.
 *
 * @return array{article: ArticleModel, project: ProjectModel}
 */
function seedDailyGenerationFixtures(): array
{
    $article = ArticleModel::factory()->published()->create(['slug' => 'payments']);
    ArticleTranslationModel::factory()->forLocale('en')->create([
        'article_id' => $article->id,
        'title' => 'Payments Gateway',
        'short_description' => 'A payments article.',
        'content' => 'Article body.',
    ]);

    $project = ProjectModel::factory()->published()->create(['slug' => 'ledger']);
    ProjectTranslationModel::factory()->forLocale('en')->create([
        'project_id' => $project->id,
        'title' => 'Ledger App',
        'brief_description' => 'A ledger app.',
    ]);

    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_type' => 'blog_article', 'page_entity_id' => $article->id, 'created_at' => '2026-07-09 10:00:00']);
    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-b', 'page_type' => 'project_detail', 'page_entity_id' => $project->id, 'created_at' => '2026-07-09 10:00:01']);
    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_type' => 'landing', 'created_at' => '2026-07-09 10:00:02']);
    analyticsEvent(['event_type' => 'time_on_page', 'ip_address' => 'ip-a', 'page_type' => 'landing', 'metadata' => ['seconds' => 10], 'created_at' => '2026-07-09 10:05:00']);
    analyticsEvent(['event_type' => 'time_on_page', 'ip_address' => 'ip-b', 'page_type' => 'project_detail', 'page_entity_id' => $project->id, 'metadata' => ['seconds' => 30], 'created_at' => '2026-07-09 10:05:01']);
    analyticsEvent(['event_type' => 'time_on_page', 'ip_address' => 'ip-a', 'page_type' => 'blog_article', 'page_entity_id' => $article->id, 'metadata' => ['seconds' => 40], 'created_at' => '2026-07-09 10:05:02']);
    analyticsEvent(['event_type' => 'time_on_page', 'ip_address' => 'ip-a', 'page_type' => 'blog_article', 'page_entity_id' => $article->id, 'metadata' => ['seconds' => 80], 'created_at' => '2026-07-09 10:05:03']);
    analyticsEvent(['event_type' => 'cta_click_cv', 'ip_address' => 'ip-a', 'page_type' => 'landing', 'metadata' => null, 'created_at' => '2026-07-09 10:06:00']);
    analyticsEvent(['event_type' => 'cta_click_theme', 'ip_address' => 'ip-a', 'page_type' => 'landing', 'metadata' => null, 'created_at' => '2026-07-09 10:06:01']);
    analyticsEvent(['event_type' => 'article_read', 'ip_address' => 'ip-a', 'metadata' => ['article_id' => $article->id, 'percentage_completed' => 50], 'created_at' => '2026-07-09 10:07:00']);
    analyticsEvent(['event_type' => 'article_read', 'ip_address' => 'ip-a', 'metadata' => ['article_id' => $article->id, 'percentage_completed' => 100], 'created_at' => '2026-07-09 10:07:01']);
    analyticsEvent(['event_type' => 'article_reaction', 'ip_address' => 'ip-a', 'metadata' => ['article_id' => $article->id, 'reaction' => 'like'], 'created_at' => '2026-07-09 10:08:00']);
    analyticsEvent(['event_type' => 'article_reaction', 'ip_address' => 'ip-b', 'metadata' => ['article_id' => $article->id, 'reaction' => 'like'], 'created_at' => '2026-07-09 10:08:01']);
    analyticsEvent(['event_type' => 'article_reaction', 'ip_address' => 'ip-c', 'metadata' => ['article_id' => $article->id, 'reaction' => 'dislike'], 'created_at' => '2026-07-09 10:08:02']);

    // The current period must be excluded.
    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-x', 'page_type' => 'blog_article', 'page_entity_id' => $article->id, 'created_at' => '2026-07-10 00:00:01']);

    FeedbackModel::factory()->create(['created_at' => '2026-07-09 11:00:00']);
    FeedbackModel::factory()->create(['created_at' => '2026-07-09 12:00:00']);
    FeedbackModel::factory()->create(['created_at' => '2026-07-08 12:00:00']);
    FeedbackModel::factory()->create(['created_at' => '2026-07-10 00:00:01']);

    return ['article' => $article, 'project' => $project];
}

/**
 * Runs the report generation for the completed day (2026-07-09).
 */
function generateDailyReports(): void
{
    app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsReportFrequency, 'daily');
    test()->travelTo('2026-07-10 00:30:00');
    test()->artisan('analytics:generate-reports')->assertSuccessful();
}

function storedReport(string $reportType): AnalyticsReportModel
{
    return AnalyticsReportModel::query()->where('report_type', $reportType)->firstOrFail();
}

it('generates the overall report for the completed day', function (): void {
    seedDailyGenerationFixtures();
    generateDailyReports();

    $overall = storedReport('overall');

    expect($overall->period_start->format('Y-m-d'))->toBe('2026-07-09')
        ->and($overall->period_end->format('Y-m-d'))->toBe('2026-07-09')
        // jsonb normalizes object key order — `toEqual` (order-insensitive).
        ->and($overall->data)->toEqual([
            'total_page_views' => ['value' => 3, 'previous' => null],
            // COUNT(DISTINCT ip_address) across ALL events in the period
            // — ip-a, ip-b (page views + reads) and ip-c (dislike).
            'unique_visitors' => ['value' => 3, 'previous' => null],
            // (10 + 30 + 40 + 80) / 4 — the article-page time_on_page
            // events feed the blog report's average read time too.
            'avg_time_on_page' => ['value' => 40.0, 'previous' => null],
            'cta_clicks' => [
                'columns' => ['target', 'count'],
                'rows' => [
                    ['target' => 'github', 'count' => 0],
                    ['target' => 'demo', 'count' => 0],
                    ['target' => 'cv', 'count' => 1],
                    ['target' => 'feedback', 'count' => 0],
                    ['target' => 'language', 'count' => 0],
                    ['target' => 'theme', 'count' => 1],
                ],
            ],
        ]);
});

it('generates the blog report for the completed day', function (): void {
    $fixtures = seedDailyGenerationFixtures();
    generateDailyReports();

    $blog = storedReport('blog');

    expect($blog->data)->toEqual([
        'total_views' => ['value' => 1, 'previous' => null],
        'unique_visitors' => ['value' => 3, 'previous' => null],
        'top_articles' => [
            'columns' => ['id', 'title', 'views', 'avg_read_time', 'completion_rate'],
            'rows' => [[
                'id' => $fixtures['article']->id,
                'title' => 'Payments Gateway',
                'views' => 1,
                // Average seconds of time_on_page on the article page.
                'avg_read_time' => 60.0,
                'completion_rate' => 75.0,
            ]],
        ],
        'reactions' => [
            'points' => [
                ['label' => 'likes', 'value' => 2],
                ['label' => 'dislikes', 'value' => 1],
            ],
        ],
    ]);
});

it('generates the projects report for the completed day', function (): void {
    $fixtures = seedDailyGenerationFixtures();
    generateDailyReports();

    $projects = storedReport('projects');

    expect($projects->data)->toEqual([
        'project_views' => [
            'columns' => ['id', 'title', 'views'],
            'rows' => [[
                'id' => $fixtures['project']->id,
                'title' => 'Ledger App',
                'views' => 1,
            ]],
        ],
    ]);
});

it('generates the feedback report for the completed day', function (): void {
    seedDailyGenerationFixtures();
    generateDailyReports();

    $feedback = storedReport('feedback');

    expect($feedback->data)->toEqual([
        // The daily period covers 2026-07-09 only — the 07-08 and
        // 07-10 submissions are excluded.
        'total_submissions' => ['value' => 2, 'previous' => null],
        'submissions_per_day' => [
            'points' => [
                ['label' => '2026-07-09', 'value' => 2],
            ],
        ],
    ]);
});

it('skips dispatch when the completed period already has reports', function (): void {
    $this->artisan('analytics:generate-reports')->assertSuccessful();
    $this->assertDatabaseCount('analytics_reports', 4);

    $this->artisan('analytics:generate-reports')
        ->expectsOutput('The current period already has reports; skipping.');

    $this->assertDatabaseCount('analytics_reports', 4);
});

it('generates the weekly report for the completed Monday-to-Sunday period', function (): void {
    $this->travelTo('2026-07-13 00:30:00');

    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_url' => 'https://example.com/', 'created_at' => '2026-07-12 23:00:00']);
    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-b', 'page_url' => 'https://example.com/', 'created_at' => '2026-07-13 00:00:01']);

    $this->artisan('analytics:generate-reports')->assertSuccessful();

    $overall = AnalyticsReportModel::query()->where('report_type', 'overall')->firstOrFail();

    expect($overall->period_start->format('Y-m-d'))->toBe('2026-07-06')
        ->and($overall->period_end->format('Y-m-d'))->toBe('2026-07-12')
        ->and($overall->data['total_page_views'])->toEqual(['value' => 1, 'previous' => null]);
});

it('generates the monthly report for the completed month', function (): void {
    app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsReportFrequency, 'monthly');
    $this->travelTo('2026-07-01 00:30:00');

    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_url' => 'https://example.com/', 'created_at' => '2026-06-30 23:00:00']);
    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-b', 'page_url' => 'https://example.com/', 'created_at' => '2026-07-01 00:00:01']);

    $this->artisan('analytics:generate-reports')->assertSuccessful();

    $overall = AnalyticsReportModel::query()->where('report_type', 'overall')->firstOrFail();

    expect($overall->period_start->format('Y-m-d'))->toBe('2026-06-01')
        ->and($overall->period_end->format('Y-m-d'))->toBe('2026-06-30')
        ->and($overall->data['total_page_views'])->toEqual(['value' => 1, 'previous' => null]);
});

it('prunes raw events older than 90 days after aggregation', function (): void {
    $this->travelTo('2026-07-10 12:00:00');

    $old = analyticsEvent(['event_type' => 'page_view', 'created_at' => '2026-04-01 00:00:00']);
    $recent = analyticsEvent(['event_type' => 'page_view', 'created_at' => '2026-07-09 10:00:00']);

    $this->artisan('analytics:generate-reports')->assertSuccessful();

    $this->assertDatabaseMissing('analytics_events', ['id' => $old->id]);
    $this->assertDatabaseHas('analytics_events', ['id' => $recent->id]);
});

it('re-aggregating the same period overwrites the stored report', function (): void {
    $period = new ReportPeriod(
        new DateTimeImmutable('2026-07-06 00:00:00'),
        new DateTimeImmutable('2026-07-12 23:59:59'),
    );

    analyticsEvent(['event_type' => 'page_view', 'created_at' => '2026-07-08 10:00:00']);

    Bus::dispatch(new AggregateAnalyticsReportsJob($period->toPeriodStart(), $period->toPeriodEnd()));

    $this->assertDatabaseCount('analytics_reports', 4);

    analyticsEvent(['event_type' => 'page_view', 'created_at' => '2026-07-08 10:00:01']);

    Bus::dispatch(new AggregateAnalyticsReportsJob($period->toPeriodStart(), $period->toPeriodEnd()));

    $this->assertDatabaseCount('analytics_reports', 4);

    $overall = AnalyticsReportModel::query()->where('report_type', 'overall')->firstOrFail();
    expect($overall->data['total_page_views'])->toEqual(['value' => 2, 'previous' => null]);
});

it('wires the previous-period values into the next report generation', function (): void {
    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_url' => 'https://example.com/', 'created_at' => '2026-07-10 10:00:00']);

    $this->travelTo('2026-07-13 00:30:00');
    $this->artisan('analytics:generate-reports')->assertSuccessful();

    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_url' => 'https://example.com/', 'created_at' => '2026-07-17 10:00:00']);
    analyticsEvent(['event_type' => 'page_view', 'ip_address' => 'ip-a', 'page_url' => 'https://example.com/', 'created_at' => '2026-07-18 10:00:00']);

    $this->travelTo('2026-07-20 00:30:00');
    $this->artisan('analytics:generate-reports')->assertSuccessful();

    $weekTwo = AnalyticsReportModel::query()
        ->where('report_type', 'overall')
        ->orderByDesc('period_start')
        ->firstOrFail();

    expect($weekTwo->period_start->format('Y-m-d'))->toBe('2026-07-13')
        ->and($weekTwo->data['total_page_views'])->toEqual(['value' => 2, 'previous' => 1]);
});

it('renders the report mail with the typed result payloads', function (): void {
    $payloads = [
        'overall' => [
            'total_page_views' => ['value' => 3, 'previous' => null],
            'unique_visitors' => ['value' => 2, 'previous' => null],
            'avg_time_on_page' => ['value' => 40.0, 'previous' => null],
            'cta_clicks' => [
                'columns' => ['target', 'count'],
                'rows' => [['target' => 'cv', 'count' => 1]],
            ],
        ],
        'blog' => [
            'total_views' => ['value' => 1, 'previous' => null],
            'unique_visitors' => ['value' => 1, 'previous' => null],
            'top_articles' => [
                'columns' => ['id', 'title', 'views', 'avg_read_time', 'completion_rate'],
                'rows' => [[
                    'id' => 1,
                    'title' => 'Payments',
                    'views' => 1,
                    'avg_read_time' => 60.0,
                    'completion_rate' => 75.0,
                ]],
            ],
            'reactions' => [
                'points' => [
                    ['label' => 'likes', 'value' => 2],
                    ['label' => 'dislikes', 'value' => 1],
                ],
            ],
        ],
        'projects' => [
            'project_views' => [
                'columns' => ['id', 'title', 'views'],
                'rows' => [['id' => 1, 'title' => 'Ledger', 'views' => 1]],
            ],
        ],
        'feedback' => [
            'total_submissions' => ['value' => 2, 'previous' => null],
            'submissions_per_day' => [
                'points' => [['label' => '2026-07-09', 'value' => 2]],
            ],
        ],
    ];

    $mail = new SendAnalyticsReportMail(
        $payloads,
        new DateTimeImmutable('2026-07-06'),
        new DateTimeImmutable('2026-07-12'),
    );

    expect($mail->envelope()->subject)->toBe('Analytics report — Jul 6 – Jul 12, 2026');

    $html = view('mail.analytics-report', ['reports' => $payloads, 'periodLabel' => $mail->periodLabel()])->render();

    expect($html)->toContain('Jul 6 – Jul 12, 2026')
        ->and($html)->toContain('Payments')
        ->and($html)->toContain('Ledger')
        ->and($html)->toContain('2026-07-09');
});

it('queues the report mail when emailing is enabled with a recipient', function (): void {
    app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailEnabled, true);
    app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailRecipient, 'admin@example.com');

    Mail::fake();

    $this->artisan('analytics:generate-reports')->assertSuccessful();

    Mail::assertQueued(SendAnalyticsReportMail::class, fn (SendAnalyticsReportMail $mail): bool => $mail->to[0]['address'] === 'admin@example.com');
});

it('does not send mail when emailing is disabled', function (): void {
    app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailEnabled, false);
    app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailRecipient, 'admin@example.com');

    Mail::fake();

    $this->artisan('analytics:generate-reports')->assertSuccessful();

    Mail::assertNotQueued(SendAnalyticsReportMail::class);
});

it('does not send mail without a recipient', function (): void {
    app(SettingRepositoryInterface::class)->saveValue(SettingName::AnalyticsEmailEnabled, true);

    Mail::fake();

    $this->artisan('analytics:generate-reports')->assertSuccessful();

    Mail::assertNotQueued(SendAnalyticsReportMail::class);
});

it('dispatches the job for the current period from the Generate Now endpoint', function (): void {
    $this->loginAsAdmin($this->createAdmin());

    Queue::fake();
    $this->travelTo('2026-07-15 10:00:00');

    $this->postJson('/admin/analytics/generate-reports')
        ->assertStatus(202)
        ->assertJson(['dispatched' => true]);

    Queue::assertPushed(AggregateAnalyticsReportsJob::class, fn (AggregateAnalyticsReportsJob $job): bool => $job->periodStart->format('Y-m-d') === '2026-07-13'
        && $job->periodEnd->format('Y-m-d') === '2026-07-19');
});

it('requires authentication for the Generate Now endpoint', function (): void {
    $this->postJson('/admin/analytics/generate-reports')
        ->assertStatus(401);
});
