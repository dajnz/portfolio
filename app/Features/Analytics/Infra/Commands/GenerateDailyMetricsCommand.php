<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Commands;

use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use App\Features\Analytics\Infra\Jobs\AggregateDailyMetricsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

/**
 * `analytics:generate-daily-metrics` (analytics.md:182-184,
 * backup.md:463): the thin scheduler entry point — resolves the date
 * range (the previous day + the in-progress day,
 * `AggregateDailyMetricsUseCase::defaultDateRange()`) and dispatches
 * the queued aggregation job. The job does the work; the command does
 * not block the scheduler. Registered in `routes/console.php` at
 * `0 2 * * *` (dailyAt 02:00), superseding ADR-0012's old every-4-hours
 * analytics cadence.
 */
final class GenerateDailyMetricsCommand extends Command
{
    protected $signature = 'analytics:generate-daily-metrics';

    protected $description = 'Dispatch the daily metrics aggregation for the previous and in-progress days';

    public function handle(AggregateDailyMetricsUseCase $aggregate): int
    {
        [$dateFrom, $dateTo] = $aggregate->defaultDateRange();

        Bus::dispatch(new AggregateDailyMetricsJob($dateFrom, $dateTo));

        $this->info(
            sprintf(
                'Dispatched daily metrics aggregation for %s – %s.',
                $dateFrom->format('Y-m-d'),
                $dateTo->format('Y-m-d'),
            ),
        );

        return self::SUCCESS;
    }
}
