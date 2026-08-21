<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Jobs;

use App\Features\Analytics\App\UseCases\GenerateReportsUseCase;
use App\Features\Analytics\App\UseCases\PruneEventsUseCase;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Infra\Mail\SendAnalyticsReportMail;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * The aggregation job (architecture.md:171, analytics.md:154-194):
 * dispatches `GenerateReportsUseCase` for the given period, prunes raw
 * events older than 90 days after successful aggregation (B37 — the
 * cutoff is the fixed constant below, the config key is informational),
 * and — when `analytics_email_enabled` is on with a recipient — queues
 * `SendAnalyticsReportMail`. Dispatched by `analytics:generate-reports`
 * (period boundary check in the command) and by the dashboard
 * "Generate Now" endpoint (bypassing the check, B220).
 */
final class AggregateAnalyticsReportsJob implements ShouldQueue
{
    public const EVENT_RETENTION_DAYS = 90;

    public function __construct(
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
    ) {
    }

    public function handle(
        GenerateReportsUseCase $generate,
        PruneEventsUseCase $pruneEvents,
        SettingRepositoryInterface $settings,
    ): void {
        $period = new ReportPeriod($this->periodStart, $this->periodEnd);
        $reports = $generate->generate($period);

        $pruneEvents->prune(self::EVENT_RETENTION_DAYS, now()->toDateTimeImmutable());

        $enabled = (bool) $settings->value(SettingName::AnalyticsEmailEnabled, false);
        $recipient = (string) $settings->value(SettingName::AnalyticsEmailRecipient, '');

        if ($enabled && $recipient !== '') {
            Mail::to($recipient)->send(
                new SendAnalyticsReportMail($reports, $this->periodStart, $this->periodEnd),
            );
        }
    }
}
