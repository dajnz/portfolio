<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\Infra\Listeners\LoginListener;
use App\Features\Auth\Infra\Repositories\EloquentAdminRepository;
use App\Features\Auth\Infra\Repositories\EloquentAdminTimedOtpRepository;
use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Infra\Repositories\EloquentAnalyticsDailyMetricRepository;
use App\Features\Analytics\Infra\Repositories\EloquentAnalyticsEventRepository;
use App\Features\Analytics\Infra\Repositories\EloquentAnalyticsReportRepository;
use App\Features\Analytics\Infra\Services\EloquentAnalyticsReportDataReader;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleReactionRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleTagRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\PublicArticleReadRepositoryInterface;
use App\Features\Blog\Infra\Repositories\ArticleListQueryRepositoryInterface;
use App\Features\Blog\Infra\Repositories\EloquentArticleListQueryRepository;
use App\Features\Blog\Infra\Repositories\EloquentArticleReactionRepository;
use App\Features\Blog\Infra\Repositories\EloquentArticleRepository;
use App\Features\Blog\Infra\Repositories\EloquentArticleTagRepository;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Experience\Infra\Repositories\EloquentTimelineItemRepository;
use App\Features\Experience\Infra\Repositories\TimelineItemListQueryRepositoryInterface;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;
use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;
use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use App\Features\Portfolio\Infra\Repositories\ContactListQueryRepositoryInterface;
use App\Features\Portfolio\Infra\Repositories\EloquentAboutInfoRepository;
use App\Features\Portfolio\Infra\Repositories\EloquentContactRepository;
use App\Features\Portfolio\Infra\Repositories\EloquentFeedbackRepository;
use App\Features\Portfolio\Infra\Repositories\FeedbackListQueryRepositoryInterface;
use App\Features\Projects\App\Contracts\PublishedArticleReaderInterface;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Projects\Infra\Repositories\EloquentProjectListQueryRepository;
use App\Features\Projects\Infra\Repositories\EloquentProjectRepository;
use App\Features\Projects\Infra\Repositories\ProjectListQueryRepositoryInterface;
use App\Features\Projects\Infra\Services\PublishedArticleReader;
use App\Features\Resume\App\Contracts\ResumePdfRendererInterface;
use App\Features\Resume\Domain\RepositoryInterfaces\ResumeConfigRepositoryInterface;
use App\Features\Resume\Infra\Repositories\EloquentResumeConfigRepository;
use App\Features\Resume\Infra\Repositories\EloquentResumeDataReader;
use App\Features\Resume\Infra\Repositories\ResumeDataReaderInterface;
use App\Features\Resume\Infra\Services\ResumePdfRenderer;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Settings\Infra\Repositories\EloquentFeatureFlagRepository;
use App\Features\Settings\Infra\Repositories\EloquentSettingRepository;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\MarkdownContentPipelineInterface;
use App\Features\Shared\App\Contracts\PreviewUrlGeneratorInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\ProjectReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\PublishedArticleReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Infra\Repositories\EloquentAboutInfoReadRepository;
use App\Features\Shared\Infra\Repositories\EloquentProjectReadRepository;
use App\Features\Shared\Infra\Repositories\EloquentPublishedArticleReadRepository;
use App\Features\Shared\Infra\Repositories\EloquentTechSkillReadRepository;
use App\Features\Shared\Infra\Services\AdminListCountCacheInvalidator;
use App\Features\Shared\Infra\Services\AdminListPaginator;
use App\Features\Shared\Infra\Services\LaravelPreviewUrlGenerator;
use App\Features\Shared\Infra\Services\MarkdownContentPipeline;
use App\Features\Shared\Infra\Services\RustFsAssetCleaner;
use App\Features\Shared\Infra\Services\SitemapService;
use App\Features\Shared\Infra\Swagger\SwaggerUiService;
use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;
use App\Features\TechSkills\Infra\Repositories\EloquentTechSkillWriteRepository;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TechSkillReadRepositoryInterface::class,
            EloquentTechSkillReadRepository::class,
        );

        $this->app->bind(
            SettingRepositoryInterface::class,
            EloquentSettingRepository::class,
        );

        $this->app->bind(
            FeatureFlagRepositoryInterface::class,
            EloquentFeatureFlagRepository::class,
        );

        $this->app->bind(
            AdminRepositoryInterface::class,
            EloquentAdminRepository::class,
        );

        $this->app->bind(
            AdminTimedOtpRepositoryInterface::class,
            EloquentAdminTimedOtpRepository::class,
        );

        $this->app->bind(
            AboutInfoRepositoryInterface::class,
            EloquentAboutInfoRepository::class,
        );

        $this->app->bind(
            ContactRepositoryInterface::class,
            EloquentContactRepository::class,
        );

        $this->app->bind(
            FeedbackRepositoryInterface::class,
            EloquentFeedbackRepository::class,
        );

        $this->app->bind(
            ContactListQueryRepositoryInterface::class,
            EloquentContactRepository::class,
        );

        $this->app->bind(
            FeedbackListQueryRepositoryInterface::class,
            EloquentFeedbackRepository::class,
        );

        $this->app->bind(
            ProjectReadRepositoryInterface::class,
            EloquentProjectReadRepository::class,
        );

        $this->app->bind(
            TechSkillWriteRepositoryInterface::class,
            EloquentTechSkillWriteRepository::class,
        );

        $this->app->bind(
            TimelineItemRepositoryInterface::class,
            EloquentTimelineItemRepository::class,
        );

        $this->app->bind(
            TimelineItemListQueryRepositoryInterface::class,
            EloquentTimelineItemRepository::class,
        );

        $this->app->bind(
            ProjectRepositoryInterface::class,
            EloquentProjectRepository::class,
        );

        $this->app->bind(
            ArticleRepositoryInterface::class,
            EloquentArticleRepository::class,
        );

        $this->app->bind(
            ArticleTagRepositoryInterface::class,
            EloquentArticleTagRepository::class,
        );

        $this->app->bind(
            ArticleReactionRepositoryInterface::class,
            EloquentArticleReactionRepository::class,
        );

        $this->app->bind(
            ArticleListQueryRepositoryInterface::class,
            EloquentArticleListQueryRepository::class,
        );

        $this->app->bind(
            ProjectListQueryRepositoryInterface::class,
            EloquentProjectListQueryRepository::class,
        );

        $this->app->bind(
            AnalyticsEventRepositoryInterface::class,
            EloquentAnalyticsEventRepository::class,
        );

        $this->app->bind(
            AnalyticsDailyMetricRepositoryInterface::class,
            EloquentAnalyticsDailyMetricRepository::class,
        );

        $this->app->bind(
            AnalyticsReportRepositoryInterface::class,
            EloquentAnalyticsReportRepository::class,
        );

        $this->app->bind(
            AnalyticsReportDataReaderInterface::class,
            EloquentAnalyticsReportDataReader::class,
        );

        $this->app->bind(
            PublishedArticleReaderInterface::class,
            PublishedArticleReader::class,
        );

        $this->app->bind(
            PreviewUrlGeneratorInterface::class,
            LaravelPreviewUrlGenerator::class,
        );

        $this->app->bind(
            CountCacheInvalidatorInterface::class,
            AdminListCountCacheInvalidator::class,
        );

        $this->app->bind(
            AboutInfoReadRepositoryInterface::class,
            EloquentAboutInfoReadRepository::class,
        );

        $this->app->bind(
            PublishedArticleReadRepositoryInterface::class,
            EloquentPublishedArticleReadRepository::class,
        );

        $this->app->bind(
            AdminListPaginatorInterface::class,
            fn (): AdminListPaginator => new AdminListPaginator(
                // The mockup's DataTable (projects.html) has no
                // sortable columns — the list stays on the paginator's
                // default `id` order.
                allowedSortColumns: [],
            ),
        );

        $this->app->bind(
            AssetCleanerInterface::class,
            RustFsAssetCleaner::class,
        );

        $this->app->bind(
            SitemapInvalidatorInterface::class,
            SitemapService::class,
        );

        // The content pipeline (B23) is a singleton: the underlying
        // highlighter registers all 185 languages on construction, so
        // the graph is built once per process, not per request. The
        // app layer consumes it via the app-layer contract.
        $this->app->singleton(
            MarkdownContentPipelineInterface::class,
            MarkdownContentPipeline::class,
        );

        // The public blog read model (step 8.2): the DOMAIN contract
        // is implemented by the same Eloquent repository that serves
        // the admin list-query contract — the app layer depends on
        // the domain interface only.
        $this->app->bind(
            PublicArticleReadRepositoryInterface::class,
            EloquentArticleListQueryRepository::class,
        );

        $this->app->bind(
            ResumeConfigRepositoryInterface::class,
            EloquentResumeConfigRepository::class,
        );

        $this->app->bind(
            ResumeDataReaderInterface::class,
            EloquentResumeDataReader::class,
        );

        $this->app->bind(
            ResumePdfRendererInterface::class,
            ResumePdfRenderer::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // authentication.md:21-27 — 5 attempts per minute per IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('totp', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // authentication.md:333-343 — layered feedback protection: layer 1
        // is this per-IP limiter (5/min), layer 2 is the
        // `rate_limit.fingerprint` middleware (3/h per IP+UA fingerprint).
        RateLimiter::for('feedback', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // analytics.md:52 — event ingestion cap (60/min/IP).
        RateLimiter::for('analytics', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // authentication.md:363 — repopulates `session_version` after
        // remember-me (recaller cookie) re-authentication.
        Event::listen(Login::class, LoginListener::class);

        // openapi.md:269-275 — the Swagger UI (l5-swagger) serves the
        // dereferenced `docs/openapi/openapi.yaml` contract. Production
        // skips the sync (the package is a require-dev dependency).
        if (! app()->isProduction()) {
            app(SwaggerUiService::class)->sync();
        }

        $this->commands([
            \App\Console\Commands\ExportLangCommand::class,
            \App\Features\Settings\Infra\Commands\ResetMaintenanceCommand::class,
            \App\Features\Auth\Infra\Commands\CreateAdminCommand::class,
            \App\Features\Auth\Infra\Commands\ResetAdminPasswordCommand::class,
            \App\Features\Shared\Infra\Commands\CleanupOrphanedFilesCommand::class,
            \App\Features\Shared\Infra\Commands\PruneTrashedItemsCommand::class,
            \App\Features\Analytics\Infra\Commands\PruneAnalyticsEventsCommand::class,
            \App\Features\Analytics\Infra\Commands\GenerateDailyMetricsCommand::class,
            \App\Features\Analytics\Infra\Commands\GenerateAnalyticsReportsCommand::class,
        ]);
    }
}
