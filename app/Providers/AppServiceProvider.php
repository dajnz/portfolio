<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Auth\App\Contracts\TotpLockoutMessengerInterface;
use App\Features\Auth\App\Contracts\TotpServiceInterface;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\Infra\Listeners\LoginListener;
use App\Features\Auth\Infra\Repositories\EloquentAdminRepository;
use App\Features\Auth\Infra\Repositories\EloquentAdminTimedOtpRepository;
use App\Features\Auth\Infra\Services\AdminAuthService;
use App\Features\Auth\Infra\Services\TotpService;
use App\Features\Analytics\App\Contracts\AnalyticsDigestMailerInterface;
use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\App\Contracts\ReportsRebuildSchedulerInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Infra\Mail\LaravelAnalyticsDigestMailer;
use App\Features\Analytics\Infra\Repositories\EloquentAnalyticsDailyMetricRepository;
use App\Features\Analytics\Infra\Repositories\EloquentAnalyticsEventRepository;
use App\Features\Analytics\Infra\Repositories\EloquentAnalyticsReportRepository;
use App\Features\Analytics\Infra\Services\EloquentAnalyticsReportDataReader;
use App\Features\Analytics\Infra\Services\LaravelReportsRebuildScheduler;
use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;
use App\Features\Backup\App\Contracts\BackupArchiveInterface;
use App\Features\Backup\App\Contracts\BackupJobDispatcherInterface;
use App\Features\Backup\App\Contracts\StorageBatchTransferable;
use App\Features\Backup\App\Contracts\StorageEstimatorInterface;
use App\Features\Backup\App\Contracts\TableTruncatorInterface;
use App\Features\Backup\App\DTOs\BackupSources;
use App\Features\Backup\App\Services\BackupWorkflowService;
use App\Features\Backup\App\Services\DatabaseRestoreService;
use App\Features\Backup\App\Services\RestoreWorkflowService;
use App\Features\Backup\App\UseCases\CreateBackupUseCase;
use App\Features\Backup\App\UseCases\PruneBackupsUseCase;
use App\Features\Backup\App\UseCases\RestoreBackupUseCase;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use App\Features\Backup\Infra\Archive\ZipBackupArchive;
use App\Features\Backup\Infra\Persistence\LaravelTableTruncator;
use App\Features\Backup\Infra\Repositories\EloquentBackupRepository;
use App\Features\Backup\Infra\Repositories\EloquentEncryptionKeyRepository;
use App\Features\Backup\Infra\Services\BackupTableFactory;
use App\Features\Backup\Infra\Services\QueueBackupJobDispatcher;
use App\Features\Backup\Infra\Storage\RustFsAssetPathCollector;
use App\Features\Backup\Infra\Storage\RustFsBatchTransfer;
use App\Features\Backup\Infra\Storage\StorageEstimator;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleReactionRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\ArticleTagRepositoryInterface;
use App\Features\Blog\Domain\RepositoryInterfaces\PublicArticleReadRepositoryInterface;
use App\Features\Blog\App\Contracts\ArticleListQueryRepositoryInterface;
use App\Features\Blog\App\Contracts\ArticleViewRecorderInterface;
use App\Features\Blog\App\Contracts\VisitorFingerprintInterface;
use App\Features\Blog\Infra\Repositories\EloquentArticleListQueryRepository;
use App\Features\Blog\Infra\Repositories\EloquentArticleReactionRepository;
use App\Features\Blog\Infra\Repositories\EloquentArticleRepository;
use App\Features\Blog\Infra\Repositories\EloquentArticleTagRepository;
use App\Features\Blog\Infra\Services\LaravelArticleViewRecorder;
use App\Features\Blog\Infra\Services\LaravelVisitorFingerprint;
use App\Features\Experience\Domain\RepositoryInterfaces\TimelineItemRepositoryInterface;
use App\Features\Experience\App\Services\TimelineContinuityService;
use App\Features\Experience\Infra\Repositories\EloquentTimelineItemRepository;
use App\Features\Experience\App\Contracts\TimelineItemListQueryRepositoryInterface;
use App\Features\Experience\App\UseCases\ListTimelineItemsUseCase;
use App\Features\Portfolio\App\Contracts\DashboardStatsReaderInterface;
use App\Features\Portfolio\App\Contracts\FeedbackNotificationMailerInterface;
use App\Features\Portfolio\App\Contracts\ResumeModeReaderInterface;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;
use App\Features\Portfolio\Domain\RepositoryInterfaces\ContactRepositoryInterface;
use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use App\Features\Portfolio\Infra\Mail\LaravelFeedbackNotificationMailer;
use App\Features\Portfolio\App\Contracts\ContactListQueryRepositoryInterface;
use App\Features\Portfolio\App\UseCases\ListContactsUseCase;
use App\Features\Portfolio\Infra\Repositories\EloquentAboutInfoRepository;
use App\Features\Portfolio\Infra\Repositories\EloquentContactRepository;
use App\Features\Portfolio\Infra\Repositories\EloquentFeedbackRepository;
use App\Features\Portfolio\App\Contracts\FeedbackListQueryRepositoryInterface;
use App\Features\Portfolio\Infra\Services\DashboardStatsReader;
use App\Features\Portfolio\Infra\Services\ResumeModeReader;
use App\Features\Projects\App\Contracts\PublishedArticleReaderInterface;
use App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface;
use App\Features\Projects\Infra\Repositories\EloquentProjectListQueryRepository;
use App\Features\Projects\Infra\Repositories\EloquentProjectRepository;
use App\Features\Projects\App\Contracts\ProjectListQueryRepositoryInterface;
use App\Features\Projects\Infra\Services\PublishedArticleReader;
use App\Features\Resume\App\Contracts\ResumePdfCacheInterface;
use App\Features\Resume\App\Contracts\ResumePdfCacheInvalidatorInterface;
use App\Features\Resume\App\Contracts\ResumePdfRendererInterface;
use App\Features\Resume\Domain\RepositoryInterfaces\ResumeConfigRepositoryInterface;
use App\Features\Resume\Infra\Repositories\EloquentResumeConfigRepository;
use App\Features\Resume\Infra\Repositories\EloquentResumeDataReader;
use App\Features\Resume\App\Contracts\ResumeDataReaderInterface;
use App\Features\Resume\Infra\Services\LaravelResumePdfCache;
use App\Features\Resume\Infra\Services\ResumePdfCacheInvalidator;
use App\Features\Resume\Infra\Services\ResumePdfRenderer;
use App\Features\Settings\App\Contracts\MailTransportTesterInterface;
use App\Features\Settings\Domain\RepositoryInterfaces\FeatureFlagRepositoryInterface;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Settings\Infra\Repositories\EloquentFeatureFlagRepository;
use App\Features\Settings\Infra\Repositories\EloquentSettingRepository;
use App\Features\Settings\Infra\Services\HttpMailTransportTester;
use App\Features\Shared\App\Contracts\ActiveLocaleInterface;
use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\Contracts\AdminSessionInterface;
use App\Features\Shared\App\Contracts\AnalyticsConfigInterface;
use App\Features\Shared\App\Contracts\ApplicationUrlInterface;
use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use App\Features\Shared\App\Contracts\AssetStorageInterface;
use App\Features\Shared\App\Contracts\CacheClearerInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\EncrypterInterface;
use App\Features\Shared\App\Contracts\LoggerInterface;
use App\Features\Shared\App\Contracts\MaintenanceModeInterface;
use App\Features\Shared\App\Contracts\MarkdownContentPipelineInterface;
use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;
use App\Features\Shared\App\Contracts\PasswordHasherInterface;
use App\Features\Shared\App\Contracts\PreviewUrlGeneratorInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;
use App\Features\Shared\App\Contracts\TransactionManagerInterface;
use App\Features\Shared\App\Contracts\TranslatorInterface;
use App\Features\Shared\App\Contracts\UploadDefaultsInterface;
use App\Features\Shared\App\Contracts\UrlGeneratorInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\ContactReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\ProjectReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\PublishedArticleReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Infra\Logging\LaravelLogger;
use App\Features\Shared\Infra\Repositories\EloquentAboutInfoReadRepository;
use App\Features\Shared\Infra\Repositories\EloquentContactReadRepository;
use App\Features\Shared\Infra\Repositories\EloquentProjectReadRepository;
use App\Features\Shared\Infra\Repositories\EloquentPublishedArticleReadRepository;
use App\Features\Shared\Infra\Repositories\EloquentSettingsReadRepository;
use App\Features\Shared\Infra\Repositories\EloquentTechSkillReadRepository;
use App\Features\Shared\Infra\Services\ActiveLocaleService;
use App\Features\Shared\Infra\Services\AdminListCountCacheInvalidator;
use App\Features\Shared\Infra\Services\AdminListPaginator;
use App\Features\Shared\Infra\Services\AppVersion;
use App\Features\Shared\Infra\Services\GitVersionReader;
use App\Features\Shared\Infra\Services\LaravelAdminSession;
use App\Features\Shared\Infra\Services\LaravelAnalyticsConfig;
use App\Features\Shared\Infra\Services\LaravelApplicationUrl;
use App\Features\Shared\Infra\Services\LaravelCacheClearer;
use App\Features\Shared\Infra\Services\LaravelEncrypter;
use App\Features\Shared\Infra\Services\LaravelMaintenanceMode;
use App\Features\Shared\Infra\Services\LaravelPaginationDefaults;
use App\Features\Shared\Infra\Services\LaravelPasswordHasher;
use App\Features\Shared\Infra\Services\LaravelPreviewUrlGenerator;
use App\Features\Shared\Infra\Services\LaravelTransactionManager;
use App\Features\Shared\Infra\Services\LaravelTranslator;
use App\Features\Shared\Infra\Services\LaravelUploadDefaults;
use App\Features\Shared\Infra\Services\LaravelUrlGenerator;
use App\Features\Shared\Infra\Services\MarkdownContentPipeline;
use App\Features\Shared\Infra\Services\RustFsAssetCleaner;
use App\Features\Shared\Infra\Services\RustFsAssetStorage;
use App\Features\Shared\Infra\Services\SitemapService;
use App\Features\Shared\Infra\Services\UploadedImageProcessor;
use App\Features\Shared\Infra\Swagger\SwaggerUiService;
use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;
use App\Features\TechSkills\Infra\Repositories\EloquentTechSkillWriteRepository;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\App as AppFacade;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
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

        // The upload WebP pipeline (Shared Infra) — GD driver: the decode
        // applies the EXIF auto-orientation, WebP encode strips metadata.
        $this->app->bind(
            UploadedImageProcessor::class,
            fn (): UploadedImageProcessor => new UploadedImageProcessor(
                new ImageManager(new Driver()),
            ),
        );

        $this->app->bind(
            AboutInfoReadRepositoryInterface::class,
            EloquentAboutInfoReadRepository::class,
        );

        $this->app->bind(
            ContactReadRepositoryInterface::class,
            EloquentContactReadRepository::class,
        );

        $this->app->bind(
            PublishedArticleReadRepositoryInterface::class,
            EloquentPublishedArticleReadRepository::class,
        );

        $this->app->bind(
            AdminListPaginatorInterface::class,
            fn (): AdminListPaginator => new AdminListPaginator(
                // No sortable columns — the list stays on the paginator's
                // default `id` order.
                allowedSortColumns: [],
            ),
        );

        // The lists whose sort differs from the id default get their own
        // configured paginator: the timeline defaults to date desc and
        // allows the date column, the reorderable contacts list allows
        // `sort_order`.
        $this->app->when(ListTimelineItemsUseCase::class)
            ->needs(AdminListPaginatorInterface::class)
            ->give(fn (): AdminListPaginator => new AdminListPaginator(
                allowedSortColumns: ['started_at'],
                defaultSortColumn: 'started_at',
            ));

        $this->app->when(ListContactsUseCase::class)
            ->needs(AdminListPaginatorInterface::class)
            ->give(fn (): AdminListPaginator => new AdminListPaginator(
                allowedSortColumns: ['sort_order'],
            ));

        $this->app->bind(
            AssetCleanerInterface::class,
            RustFsAssetCleaner::class,
        );

        $this->app->bind(
            SitemapInvalidatorInterface::class,
            SitemapService::class,
        );

        // Framework-capability contracts the app layer consumes instead of
        // reading configuration, the locale or the connection directly: the
        // pagination defaults, the site's base URL, the analytics pipeline
        // configuration, the transactional connection, the active locale
        // context, absolute path URLs, the session store, the auth guard, the
        // password hasher, the encrypter, the asset storage and the
        // translator.
        $this->app->bind(
            PaginationDefaultsInterface::class,
            LaravelPaginationDefaults::class,
        );

        $this->app->bind(
            UploadDefaultsInterface::class,
            LaravelUploadDefaults::class,
        );

        $this->app->bind(
            ApplicationUrlInterface::class,
            LaravelApplicationUrl::class,
        );

        $this->app->bind(
            AnalyticsConfigInterface::class,
            LaravelAnalyticsConfig::class,
        );

        $this->app->bind(
            TransactionManagerInterface::class,
            LaravelTransactionManager::class,
        );

        $this->app->bind(
            ActiveLocaleInterface::class,
            ActiveLocaleService::class,
        );

        $this->app->bind(
            UrlGeneratorInterface::class,
            LaravelUrlGenerator::class,
        );

        $this->app->bind(
            TranslatorInterface::class,
            LaravelTranslator::class,
        );

        // The continuity service takes an explicit message resolver (kept as a
        // callable so unit tests stay framework-free); the container wires it
        // to the app-layer translator.
        $this->app->bind(
            TimelineContinuityService::class,
            fn ($app): TimelineContinuityService => new TimelineContinuityService(
                $app->make(TimelineItemRepositoryInterface::class),
                fn (string $key): string => $app->make(TranslatorInterface::class)->translate($key),
            ),
        );

        $this->app->bind(
            AdminSessionInterface::class,
            LaravelAdminSession::class,
        );

        $this->app->bind(
            PasswordHasherInterface::class,
            LaravelPasswordHasher::class,
        );

        $this->app->bind(
            EncrypterInterface::class,
            LaravelEncrypter::class,
        );

        $this->app->bind(
            AssetStorageInterface::class,
            RustFsAssetStorage::class,
        );

        // Feature-level framework-capability ports: the analytics digest
        // and feedback notification mailers, the resume PDF cache (and its
        // invalidator), the mail-transport probe, the TOTP service and its
        // lockout messenger, and the Portfolio cross-feature reads.
        $this->app->bind(
            AnalyticsDigestMailerInterface::class,
            LaravelAnalyticsDigestMailer::class,
        );

        $this->app->bind(
            ReportsRebuildSchedulerInterface::class,
            LaravelReportsRebuildScheduler::class,
        );

        $this->app->bind(
            ArticleViewRecorderInterface::class,
            LaravelArticleViewRecorder::class,
        );

        $this->app->bind(
            VisitorFingerprintInterface::class,
            LaravelVisitorFingerprint::class,
        );

        $this->app->bind(
            FeedbackNotificationMailerInterface::class,
            LaravelFeedbackNotificationMailer::class,
        );

        $this->app->bind(
            ResumePdfCacheInterface::class,
            LaravelResumePdfCache::class,
        );

        $this->app->bind(
            ResumePdfCacheInvalidatorInterface::class,
            ResumePdfCacheInvalidator::class,
        );

        $this->app->bind(
            MailTransportTesterInterface::class,
            HttpMailTransportTester::class,
        );

        $this->app->bind(
            TotpServiceInterface::class,
            TotpService::class,
        );

        $this->app->bind(
            TotpLockoutMessengerInterface::class,
            AdminAuthService::class,
        );

        $this->app->bind(
            ResumeModeReaderInterface::class,
            ResumeModeReader::class,
        );

        $this->app->bind(
            DashboardStatsReaderInterface::class,
            DashboardStatsReader::class,
        );

        // The content pipeline is a singleton: the underlying
        // highlighter registers all 185 languages on construction, so
        // the graph is built once per process, not per request. The
        // app layer consumes it via the app-layer contract.
        $this->app->singleton(
            MarkdownContentPipelineInterface::class,
            MarkdownContentPipeline::class,
        );

        // The app-layer logger contract: app-layer services log through
        // it instead of the framework `Log` facade; the Infra adapter
        // wraps the framework logging stack (`channel()` scopes to a
        // named channel).
        $this->app->bind(
            LoggerInterface::class,
            fn ($app): LaravelLogger => new LaravelLogger(
                $app->make(\Illuminate\Log\LogManager::class),
            ),
        );

        // The public blog read model: the DOMAIN contract
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

        // The Backup feature's repositories + workflow services. The
        // storage surfaces: `Filesystem` (the contract) resolves to the
        // private `backups` disk (its only consumer — the archives live
        // there); the transfers are per-bucket instances — the
        // assets one is the named `backup.assets-transfer` binding, the
        // interface binds the backups bucket. The workflows consume
        // only app-layer contracts: the per-table readers/exporters/
        // writers arrive as ready bundles from `BackupTableFactory`
        // and the config-derived values via the `BackupSources`
        // DTO. The Shared cross-feature surfaces sit in front
        // of the Settings feature.
        $this->app->bind(
            BackupRepositoryInterface::class,
            EloquentBackupRepository::class,
        );

        $this->app->bind(
            EncryptionKeyRepositoryInterface::class,
            EloquentEncryptionKeyRepository::class,
        );

        $this->app->bind(
            AssetPathCollectorInterface::class,
            RustFsAssetPathCollector::class,
        );

        $this->app->bind(
            BackupArchiveInterface::class,
            fn (): ZipBackupArchive => new ZipBackupArchive(
                encryptionEnabled: Config::get('backup.encryption_enabled', true),
            ),
        );

        $this->app->bind(
            StorageEstimatorInterface::class,
            fn ($app): StorageEstimator => new StorageEstimator(
                assetCollector: $app->make(AssetPathCollectorInterface::class),
                assetsDisk: $app->make('backup.assets-disk'),
                stagingPath: Config::get('backup.staging_path'),
            ),
        );

        $this->app->bind(
            TableTruncatorInterface::class,
            LaravelTableTruncator::class,
        );

        $this->app->bind(
            BackupJobDispatcherInterface::class,
            QueueBackupJobDispatcher::class,
        );

        $this->app->bind(
            Filesystem::class,
            fn ($app): \Illuminate\Contracts\Filesystem\Filesystem => $app['filesystem']->disk('rustfs-backups'),
        );

        $this->app->bind(
            StorageBatchTransferable::class,
            fn ($app): RustFsBatchTransfer => new RustFsBatchTransfer($app['filesystem']->disk('rustfs-backups')),
        );

        $this->app->bind(
            'backup.assets-transfer',
            fn ($app): RustFsBatchTransfer => new RustFsBatchTransfer($app['filesystem']->disk('rustfs')),
        );

        $this->app->bind(
            'backup.assets-disk',
            fn ($app): \Illuminate\Contracts\Filesystem\Filesystem => $app['filesystem']->disk('rustfs'),
        );

        // The Shared cross-feature surfaces: settings reads and
        // the maintenance toggle for non-Settings features, and the
        // restore's cache flush.
        $this->app->bind(
            SettingsReadRepositoryInterface::class,
            EloquentSettingsReadRepository::class,
        );

        $this->app->bind(
            MaintenanceModeInterface::class,
            LaravelMaintenanceMode::class,
        );

        $this->app->bind(
            CacheClearerInterface::class,
            LaravelCacheClearer::class,
        );

        $backupSources = fn (): BackupSources => new BackupSources(
            stagingPath: Config::get('backup.staging_path'),
            assetsBucket: Config::get('filesystems.disks.rustfs.bucket'),
        );

        $this->app->bind(
            BackupWorkflowService::class,
            fn ($app): BackupWorkflowService => new BackupWorkflowService(
                assetCollector: $app->make(AssetPathCollectorInterface::class),
                assetsTransfer: $app->make('backup.assets-transfer'),
                backupsTransfer: $app->make(StorageBatchTransferable::class),
                archive: $app->make(BackupArchiveInterface::class),
                backupRepo: $app->make(BackupRepositoryInterface::class),
                pruner: $app->make(PruneBackupsUseCase::class),
                estimator: $app->make(StorageEstimatorInterface::class),
                sources: $backupSources(),
                tables: $app->make(BackupTableFactory::class)->exportTables(),
                logger: $app->make(LoggerInterface::class),
            ),
        );

        $this->app->bind(
            DatabaseRestoreService::class,
            fn ($app): DatabaseRestoreService => new DatabaseRestoreService(
                truncator: $app->make(TableTruncatorInterface::class),
                truncateOrder: Config::get('backup.truncate_order'),
                tables: $app->make(BackupTableFactory::class)->importTables(),
            ),
        );

        $this->app->bind(
            RestoreWorkflowService::class,
            fn ($app): RestoreWorkflowService => new RestoreWorkflowService(
                backupsTransfer: $app->make(StorageBatchTransferable::class),
                assetsTransfer: $app->make('backup.assets-transfer'),
                archive: $app->make(BackupArchiveInterface::class),
                assetCollector: $app->make(AssetPathCollectorInterface::class),
                databaseRestore: $app->make(DatabaseRestoreService::class),
                maintenance: $app->make(MaintenanceModeInterface::class),
                sitemap: $app->make(SitemapInvalidatorInterface::class),
                cacheClearer: $app->make(CacheClearerInterface::class),
                logger: $app->make(LoggerInterface::class),
                sources: $backupSources(),
            ),
        );

        // The use cases whose constructor takes config-derived scalars
        // (encryption toggles, the legacy key fallback, the retention
        // fallback) are wired explicitly; the rest auto-resolve.
        $this->app->bind(
            CreateBackupUseCase::class,
            fn ($app): CreateBackupUseCase => new CreateBackupUseCase(
                backups: $app->make(BackupRepositoryInterface::class),
                keys: $app->make(EncryptionKeyRepositoryInterface::class),
                dispatcher: $app->make(BackupJobDispatcherInterface::class),
                encryptionEnabled: (bool) Config::get('backup.encryption_enabled', true),
            ),
        );

        $this->app->bind(
            RestoreBackupUseCase::class,
            fn ($app): RestoreBackupUseCase => new RestoreBackupUseCase(
                backups: $app->make(BackupRepositoryInterface::class),
                keys: $app->make(EncryptionKeyRepositoryInterface::class),
                workflow: $app->make(RestoreWorkflowService::class),
                legacyEncryptionKey: Config::get('backup.encryption_key') ?: null,
            ),
        );

        $this->app->bind(
            PruneBackupsUseCase::class,
            fn ($app): PruneBackupsUseCase => new PruneBackupsUseCase(
                backups: $app->make(BackupRepositoryInterface::class),
                settings: $app->make(SettingsReadRepositoryInterface::class),
                backupsTransfer: $app->make(StorageBatchTransferable::class),
                fallbackMaxCount: (int) Config::get('backup.max_count', 30),
            ),
        );

        // The footer version needs the repository path, which cannot be
        // auto-wired as a scalar — it is resolved from the app root.
        $this->app->bind(
            AppVersion::class,
            fn ($app): AppVersion => new AppVersion(
                reader: $app->make(GitVersionReader::class),
                basePath: $app->basePath(),
            ),
        );
    }

    public function boot(): void
    {
        // Fonts load lazily from the inlined @font-face styles
        // (`@fonts` — font-display: swap), only when a face is actually
        // rendered. The framework's per-face `<link rel=preload>`s are
        // dropped: they fetched every family/weight on every page ahead
        // of the LCP image.
        Vite::usePreloadTagAttributes(
            fn (mixed $type, ...$rest): array|false => $type === 'fonts' ? false : [],
        );

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('totp', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Layered feedback protection: layer 1
        // is this per-IP limiter (5/min), layer 2 is the
        // `rate_limit.fingerprint` middleware (3/h per IP+UA fingerprint).
        RateLimiter::for('feedback', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Event ingestion cap (60/min/IP).
        RateLimiter::for('analytics', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Repopulates `session_version` after
        // remember-me (recaller cookie) re-authentication.
        Event::listen(Login::class, LoginListener::class);

        // The Swagger UI (l5-swagger) serves the
        // dereferenced `docs/openapi/openapi.yaml` contract. Production
        // skips the sync (the package is a require-dev dependency).
        if (! AppFacade::isProduction()) {
            AppFacade::make(SwaggerUiService::class)->sync();
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
            \App\Features\Analytics\Infra\Commands\ResetAnalyticsCommand::class,
            \App\Features\Backup\Infra\Commands\CreateBackupCommand::class,
            \App\Features\Backup\Infra\Commands\RestoreBackupCommand::class,
            \App\Features\Backup\Infra\Commands\PruneBackupsCommand::class,
            \App\Features\Backup\Infra\Commands\GenerateEncryptionKeyCommand::class,
        ]);
    }
}
