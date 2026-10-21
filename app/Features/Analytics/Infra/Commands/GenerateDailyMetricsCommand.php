<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Commands;

use App\Features\Analytics\App\Contracts\DailyMetricsSchedulerInterface;
use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use Illuminate\Console\Command;

/**
 * `analytics:generate-daily-metrics`: the thin scheduler entry point —
 * resolves the date range (the previous day + the in-progress day,
 * `AggregateDailyMetricsUseCase::defaultDateRange()`) and dispatches
 * the queued aggregation job. The job does the work; the command does
 * not block the scheduler. Registered in `routes/console.php` at
 * `0 2 * * *` (dailyAt 02:00).
 */
final class GenerateDailyMetricsCommand extends Command
{
    protected $signature = 'analytics:generate-daily-metrics';

    protected $description = 'Dispatch the daily metrics aggregation for the previous and in-progress days';

    public function handle(
        AggregateDailyMetricsUseCase $aggregate,
        DailyMetricsSchedulerInterface $scheduler,
    ): int {
        [$dateFrom, $dateTo] = $aggregate->defaultDateRange();

        $scheduler->schedule($dateFrom, $dateTo);

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
