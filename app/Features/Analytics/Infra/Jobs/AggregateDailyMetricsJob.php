<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Jobs;

use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use App\Features\Analytics\App\UseCases\GetDailyDigestUseCase;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Infra\Mail\SendAnalyticsReportMail;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;

/**
 * The queued daily aggregation (analytics.md:182-184, architecture.md:171):
 * aggregates `analytics_events` (+ `feedback` rows) into
 * `analytics_daily_metrics` for the given date range — defaulting to
 * the previous day plus the in-progress day — and then dispatches the
 * report rebuild (`RebuildReportsJob`, step 11.3.3) so the reports
 * never drift from the metrics. When `analytics_email_enabled` is on
 * with a recipient set, the previous day's digest mail
 * (`SendAnalyticsReportMail`, step 11.3.5) is queued after the
 * rebuild — the digest covers the range start's day. Dispatched by
 * `analytics:generate-daily-metrics` (scheduled `0 2 * * *`).
 */
final class AggregateDailyMetricsJob implements ShouldQueue
{
    public function __construct(
        public readonly ?DateTimeImmutable $dateFrom = null,
        public readonly ?DateTimeImmutable $dateTo = null,
    ) {
    }

    public function handle(
        AggregateDailyMetricsUseCase $aggregate,
        GetDailyDigestUseCase $digest,
        SettingRepositoryInterface $settings,
    ): void {
        [$dateFrom, $dateTo] = $this->resolveDateRange();

        $aggregate->aggregate(new ReportPeriod($dateFrom, $dateTo));

        // The report rebuild (11.3.3) runs after every aggregation —
        // reports are fully rebuilt from the daily metrics, so they
        // cannot drift.
        Bus::dispatch(new RebuildReportsJob());

        $enabled = (bool) $settings->value(SettingName::AnalyticsEmailEnabled, false);
        $recipient = (string) $settings->value(SettingName::AnalyticsEmailRecipient, '');

        if ($enabled && $recipient !== '') {
            Mail::to($recipient)->send(new SendAnalyticsReportMail($digest->digest($dateFrom)));
        }
    }

    /**
     * The default range: the previous day + the in-progress day
     * (server-local timezone), so the dashboard stays live. Shared
     * with the command that dispatches this job.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public static function defaultDateRange(): array
    {
        $today = new DateTimeImmutable('today');

        return [
            $today->modify('-1 day'),
            $today->modify('+1 day')->modify('-1 second'),
        ];
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function resolveDateRange(): array
    {
        if ($this->dateFrom !== null && $this->dateTo !== null) {
            return [$this->dateFrom, $this->dateTo];
        }

        return self::defaultDateRange();
    }
}
