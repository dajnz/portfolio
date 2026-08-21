<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Commands;

use App\Features\Analytics\Domain\Enums\ReportFrequency;
use App\Features\Analytics\Infra\Jobs\AggregateAnalyticsReportsJob;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/**
 * `analytics:generate-reports` (analytics.md:156-158, backup.md:463):
 * resolves the report period from the `analytics_report_frequency`
 * setting (default weekly) and dispatches `AggregateAnalyticsReportsJob`
 * for the just-completed period (B219) — but only when the period
 * boundary has been crossed since the last generated report: the
 * comparison against the newest `analytics_reports.period_end`
 * (newest period_end >= the just-completed period's end means that
 * period was already aggregated → skip). A fresh install with no
 * reports generates on first run. The command stays thin — the job
 * does the aggregation.
 */
final class GenerateAnalyticsReportsCommand extends Command
{
    protected $signature = 'analytics:generate-reports';

    protected $description = 'Dispatch analytics report generation for the completed period';

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $frequency = ReportFrequency::fromString(
            (string) $this->settings->value(SettingName::AnalyticsReportFrequency, 'weekly'),
        );
        $period = $frequency->previousPeriod(now()->toDateTimeImmutable());
        $latestEnd = DB::table('analytics_reports')->max('period_end');

        if ($latestEnd !== null && (string) $latestEnd >= $period->toPeriodEnd()->format('Y-m-d')) {
            $this->info('The current period already has reports; skipping.');

            return self::SUCCESS;
        }

        Bus::dispatch(new AggregateAnalyticsReportsJob(
            $period->toPeriodStart(),
            $period->toPeriodEnd(),
        ));

        $this->info(
            sprintf(
                'Dispatched report generation for %s – %s.',
                $period->toPeriodStart()->format('Y-m-d'),
                $period->toPeriodEnd()->format('Y-m-d'),
            ),
        );

        return self::SUCCESS;
    }
}
