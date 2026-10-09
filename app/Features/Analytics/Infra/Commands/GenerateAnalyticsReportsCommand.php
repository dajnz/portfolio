<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Commands;

use App\Features\Analytics\Infra\Jobs\RebuildReportsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

/**
 * `analytics:generate-reports`: the manual full rebuild — thin,
 * dispatches `RebuildReportsJob` in the full-rebuild mode (recompute
 * the metrics for the still-present event window + the full feedback
 * history, then rebuild every report span). The job does the work; the
 * command is NOT scheduled.
 */
final class GenerateAnalyticsReportsCommand extends Command
{
    protected $signature = 'analytics:generate-reports';

    protected $description = 'Dispatch the full analytics rebuild — recompute the event-window metrics, then rebuild every report span';

    public function handle(): int
    {
        Bus::dispatch(new RebuildReportsJob(recomputeMetrics: true));

        $this->info('Dispatched the full analytics rebuild.');

        return self::SUCCESS;
    }
}
