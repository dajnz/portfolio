<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Infra\Jobs\RebuildReportsJob;
use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Bus;
use Tests\Support\Traits\InteractsWithAdmin;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(InteractsWithAdmin::class);

/**
 * The admin analytics dashboard (analytics.md:223-227, routes.md:136-
 * 139, B290/B293, 11.3.4): the Inertia props (whole-time blocks per
 * tab + the default chart + default rankings), the `series`/`rankings`
 * JSON endpoints with their 422 validation, the `generate-reports`
 * full-rebuild dispatch (202) and the guest redirect.
 */

beforeEach(function (): void {
    $this->repository = app(AnalyticsReportRepositoryInterface::class);
});

function seedDashboardReports(AnalyticsReportRepositoryInterface $repository): void
{
    $repository->upsertBatch([
        dashboardReport('page_views', MetricSpan::All, '2026-06-01', '2026-08-21', 100.0),
        dashboardReport('page_views', MetricSpan::Day, '2026-08-19', '2026-08-19', 10.0),
        dashboardReport('page_views', MetricSpan::Day, '2026-08-20', '2026-08-20', 20.0),
        dashboardReport('unique_visitors', MetricSpan::All, '2026-06-01', '2026-08-21', 42.0),
        dashboardReport('feedback_submissions', MetricSpan::All, '2026-06-01', '2026-08-21', 7.0),
        dashboardReport('time_on_page_seconds', MetricSpan::All, '2026-06-01', '2026-08-21', 45.0),
        dashboardReport('time_on_page_min', MetricSpan::All, '2026-06-01', '2026-08-21', 3.0),
        dashboardReport('time_on_page_max', MetricSpan::All, '2026-06-01', '2026-08-21', 240.0),
        dashboardReport('cta_clicks', MetricSpan::All, '2026-06-01', '2026-08-21', 4.0, 'github'),
        dashboardReport('cta_clicks', MetricSpan::All, '2026-06-01', '2026-08-21', 5.0, 'language'),
        dashboardReport('cta_clicks', MetricSpan::All, '2026-06-01', '2026-08-21', 1.0, 'theme'),
        dashboardReport('time_on_page_seconds', MetricSpan::All, '2026-06-01', '2026-08-21', 80.0, 'https://example.com/b'),
        dashboardReport('time_on_page_min', MetricSpan::All, '2026-06-01', '2026-08-21', 10.0, 'https://example.com/b'),
        dashboardReport('time_on_page_max', MetricSpan::All, '2026-06-01', '2026-08-21', 120.0, 'https://example.com/b'),
        dashboardReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 50.0, 'article:5'),
        dashboardReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 9.0, 'project:2'),
    ]);
}

it('renders the dashboard with the whole-time blocks, the default chart and the default rankings', function (): void {
    seedDashboardReports($this->repository);
    $this->loginAsAdmin($this->createAdmin());

    get('/admin/analytics')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Analytics/Admin/Pages/AnalyticsDashboardPage')
            ->has('overview_blocks', 6)
            ->where('overview_blocks.0', ['key' => 'total_visits', 'value' => 100])
            ->where('overview_blocks.2', ['key' => 'cta_clicks', 'value' => 10])
            ->where('overview_blocks.4', ['key' => 'language_switches', 'value' => 5])
            ->where('overview_blocks.5', ['key' => 'avg_time_on_page', 'value' => 45, 'min' => 3, 'max' => 240])
            ->has('blog_blocks', 5)
            ->where('blog_blocks.0', ['key' => 'total_views', 'value' => 0])
            ->has('projects_blocks', 2)
            ->where('projects_blocks.0', ['key' => 'total_views', 'value' => 9])
            ->where('default_chart.metric', 'page_views')
            ->has('default_chart.spans', 4)
            ->where('default_chart.spans.day.points.0', ['label' => '2026-08-19', 'value' => 10])
            ->where('default_chart.spans.day.points.1', ['label' => '2026-08-20', 'value' => 20])
            ->where('default_rankings.metric', 'time_on_page_seconds')
            ->where('default_rankings.scope', 'page')
            ->where('default_rankings.rows.top.0', [
                'page_url' => 'https://example.com/b',
                'avg' => 80,
                'min' => 10,
                'max' => 120,
            ]));
});

it('redirects guests — the Inertia page to the login, the JSON endpoints 401', function (): void {
    get('/admin/analytics')->assertRedirect();
    getJson('/admin/analytics/series?metric=page_views&span=day')->assertStatus(401);
    getJson('/admin/analytics/rankings?metric=views&variant=top&scope=article')->assertStatus(401);
    postJson('/admin/analytics/generate-reports')->assertStatus(401);
});

it('returns the metric series points at the chosen span', function (): void {
    $this->repository->upsertBatch([
        dashboardReport('page_views', MetricSpan::Week, '2026-08-10', '2026-08-16', 40.0),
        dashboardReport('page_views', MetricSpan::Week, '2026-08-17', '2026-08-23', 60.0),
    ]);
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/series?metric=page_views&span=week')
        ->assertOk()
        ->assertExactJson(['points' => [
            ['label' => '2026-08-10', 'value' => 40],
            ['label' => '2026-08-17', 'value' => 60],
        ]]);
});

it('returns the cumulative running total for the whole-time span', function (): void {
    $this->repository->upsertBatch([
        dashboardReport('page_views', MetricSpan::Day, '2026-08-19', '2026-08-19', 10.0),
        dashboardReport('page_views', MetricSpan::Day, '2026-08-20', '2026-08-20', 20.0),
    ]);
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/series?metric=page_views&span=all')
        ->assertOk()
        ->assertExactJson(['points' => [
            ['label' => '2026-08-19', 'value' => 10],
            ['label' => '2026-08-20', 'value' => 30],
        ]]);
});

it('returns the per-target cta_clicks series for the given dimension', function (): void {
    $this->repository->upsertBatch([
        dashboardReport('cta_clicks', MetricSpan::Week, '2026-08-17', '2026-08-23', 5.0, 'github'),
        dashboardReport('cta_clicks', MetricSpan::Week, '2026-08-17', '2026-08-23', 3.0, 'language'),
    ]);
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/series?metric=cta_clicks&span=week&dimension=language')
        ->assertOk()
        ->assertExactJson(['points' => [
            ['label' => '2026-08-17', 'value' => 3],
        ]]);
});

it('rejects invalid series parameters with 422', function (string $query, string $errorKey): void {
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/series'.$query)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);
})->with([
    'unknown metric' => ['?metric=not_a_metric&span=day', 'metric'],
    'missing span' => ['?metric=page_views', 'span'],
    'unknown span' => ['?metric=page_views&span=decade', 'span'],
    'cta without dimension' => ['?metric=cta_clicks&span=week', 'dimension'],
    'dimension without cta' => ['?metric=page_views&span=week&dimension=github', 'dimension'],
    'unknown dimension' => ['?metric=cta_clicks&span=week&dimension=newsletter', 'dimension'],
]);

it('returns the top and bottom time-on-page rankings with the avg, min and max', function (): void {
    $this->repository->upsertBatch([
        dashboardReport('time_on_page_seconds', MetricSpan::All, '2026-06-01', '2026-08-21', 40.0, 'https://example.com/a'),
        dashboardReport('time_on_page_min', MetricSpan::All, '2026-06-01', '2026-08-21', 5.0, 'https://example.com/a'),
        dashboardReport('time_on_page_max', MetricSpan::All, '2026-06-01', '2026-08-21', 90.0, 'https://example.com/a'),
        dashboardReport('time_on_page_seconds', MetricSpan::All, '2026-06-01', '2026-08-21', 80.0, 'https://example.com/b'),
        dashboardReport('time_on_page_min', MetricSpan::All, '2026-06-01', '2026-08-21', 10.0, 'https://example.com/b'),
        dashboardReport('time_on_page_max', MetricSpan::All, '2026-06-01', '2026-08-21', 120.0, 'https://example.com/b'),
    ]);
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/rankings?metric=time_on_page_seconds&variant=top&scope=page')
        ->assertOk()
        ->assertExactJson(['rows' => [
            ['page_url' => 'https://example.com/b', 'avg' => 80, 'min' => 10, 'max' => 120],
            ['page_url' => 'https://example.com/a', 'avg' => 40, 'min' => 5, 'max' => 90],
        ]]);

    getJson('/admin/analytics/rankings?metric=time_on_page_seconds&variant=bottom&scope=page')
        ->assertOk()
        ->assertJsonPath('rows.0.page_url', 'https://example.com/a');
});

it('returns the article rankings with the EN titles', function (): void {
    $article = ArticleModel::factory()->published()->create();
    ArticleTranslationModel::factory()->forLocale('en')->create([
        'article_id' => $article->id,
        'title' => 'Payments Gateway',
        'short_description' => 'A payments article.',
        'content' => 'The article body with enough words.',
    ]);
    ArticleTranslationModel::factory()->forLocale('ru')->create([
        'article_id' => $article->id,
        'title' => 'Платёжный шлюз',
        'short_description' => 'Статья о платежах.',
        'content' => 'Текст статьи с достаточным количеством слов.',
    ]);
    $other = ArticleModel::factory()->published()->create();
    ArticleTranslationModel::factory()->forLocale('en')->create([
        'article_id' => $other->id,
        'title' => 'Rust CLI',
        'short_description' => 'A CLI tool.',
        'content' => 'The article body with enough words.',
    ]);

    $this->repository->upsertBatch([
        dashboardReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 50.0, 'article:'.$article->id),
        dashboardReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 20.0, 'article:'.$other->id),
    ]);
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/rankings?metric=views&variant=top&scope=article')
        ->assertOk()
        ->assertExactJson(['rows' => [
            ['id' => $article->id, 'title' => 'Payments Gateway', 'value' => 50],
            ['id' => $other->id, 'title' => 'Rust CLI', 'value' => 20],
        ]]);
});

it('returns the read-time rankings with the avg, min and max joined', function (): void {
    $article = ArticleModel::factory()->published()->create();
    ArticleTranslationModel::factory()->forLocale('en')->create([
        'article_id' => $article->id,
        'title' => 'Read Me',
        'short_description' => 'A reading article.',
        'content' => 'The article body with enough words.',
    ]);
    $this->repository->upsertBatch([
        dashboardReport('read_time_seconds', MetricSpan::All, '2026-06-01', '2026-08-21', 60.0, 'article:'.$article->id),
        dashboardReport('read_time_min', MetricSpan::All, '2026-06-01', '2026-08-21', 12.0, 'article:'.$article->id),
        dashboardReport('read_time_max', MetricSpan::All, '2026-06-01', '2026-08-21', 300.0, 'article:'.$article->id),
    ]);
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/rankings?metric=read_time_seconds&variant=top&scope=article')
        ->assertOk()
        ->assertExactJson(['rows' => [[
            'id' => $article->id,
            'title' => 'Read Me',
            'avg' => 60,
            'min' => 12,
            'max' => 300,
        ]]]);
});

it('returns the project rankings with the EN titles', function (): void {
    $project = ProjectModel::factory()->published()->create();
    ProjectTranslationModel::factory()->forLocale('en')->create([
        'project_id' => $project->id,
        'title' => 'Payments Gateway',
        'brief_description' => 'A payment processing gateway.',
    ]);
    $this->repository->upsertBatch([
        dashboardReport('views', MetricSpan::All, '2026-06-01', '2026-08-21', 9.0, 'project:'.$project->id),
    ]);
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/rankings?metric=views&variant=top&scope=project')
        ->assertOk()
        ->assertExactJson(['rows' => [
            ['id' => $project->id, 'title' => 'Payments Gateway', 'value' => 9],
        ]]);
});

it('returns the most liked and most disliked articles for the reactions variants', function (): void {
    $article = ArticleModel::factory()->published()->create();
    ArticleTranslationModel::factory()->forLocale('en')->create([
        'article_id' => $article->id,
        'title' => 'Reactions Post',
        'short_description' => 'A reactions article.',
        'content' => 'The article body with enough words.',
    ]);
    $this->repository->upsertBatch([
        dashboardReport('reactions_likes', MetricSpan::All, '2026-06-01', '2026-08-21', 12.0, 'article:'.$article->id),
        dashboardReport('reactions_dislikes', MetricSpan::All, '2026-06-01', '2026-08-21', 7.0, 'article:'.$article->id),
    ]);
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/rankings?metric=reactions_likes&variant=liked&scope=article')
        ->assertOk()
        ->assertJsonPath('rows.0', ['id' => $article->id, 'title' => 'Reactions Post', 'value' => 12]);

    getJson('/admin/analytics/rankings?metric=reactions_dislikes&variant=disliked&scope=article')
        ->assertOk()
        ->assertJsonPath('rows.0.value', 7);
});

it('rejects invalid rankings parameters with 422', function (string $query, string $errorKey): void {
    $this->loginAsAdmin($this->createAdmin());

    getJson('/admin/analytics/rankings'.$query)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);
})->with([
    'unknown metric' => ['?metric=not_a_metric&variant=top&scope=page', 'metric'],
    'missing variant' => ['?metric=views&scope=article', 'variant'],
    'unknown variant' => ['?metric=views&variant=sideways&scope=article', 'variant'],
    'missing scope' => ['?metric=views&variant=top', 'scope'],
    'unknown scope' => ['?metric=views&variant=top&scope=tag', 'scope'],
    'metric not allowed at the scope' => ['?metric=views&variant=top&scope=page', 'metric'],
    'liked without the likes metric' => ['?metric=views&variant=liked&scope=article', 'variant'],
    'disliked without the dislikes metric' => ['?metric=views&variant=disliked&scope=article', 'variant'],
]);

it('dispatches the full-rebuild job and answers 202', function (): void {
    Bus::fake();
    $this->loginAsAdmin($this->createAdmin());

    postJson('/admin/analytics/generate-reports')
        ->assertStatus(202)
        ->assertExactJson(['dispatched' => true]);

    Bus::assertDispatched(
        RebuildReportsJob::class,
        static fn (RebuildReportsJob $job): bool => $job->recomputeMetrics === true,
    );
});

/**
 * Builds a report row for the dashboard/endpoint round-trips.
 */
function dashboardReport(
    string $metric,
    MetricSpan $span,
    string $periodStart,
    string $periodEnd,
    float $value,
    ?string $dimension = null,
): AnalyticsReport {
    return new AnalyticsReport(
        id: 0,
        metric: MetricName::from($metric),
        span: $span,
        dimension: $dimension,
        periodStart: new DateTimeImmutable($periodStart),
        periodEnd: new DateTimeImmutable($periodEnd),
        value: $value,
        createdAt: new DateTimeImmutable('2026-08-21 02:00:00'),
    );
}
