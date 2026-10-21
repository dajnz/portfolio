<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Commands;

use App\Features\Analytics\App\UseCases\GenerateAnalyticsReportsUseCase;
use Illuminate\Console\Command;

/**
 * `analytics:generate-reports`: the manual full rebuild — a thin shell
 * over `GenerateAnalyticsReportsUseCase`, which schedules the rebuild
 * (recompute the metrics for the still-present event window + the full
 * feedback history, then rebuild every report span). The command is NOT
 * scheduled.
 */
final class GenerateAnalyticsReportsCommand extends Command
{
    protected $signature = 'analytics:generate-reports';

    protected $description = 'Dispatch the full analytics rebuild — recompute the event-window metrics, then rebuild every report span';

    public function handle(GenerateAnalyticsReportsUseCase $generate): int
    {
        $generate->generate();

        $this->info('Dispatched the full analytics rebuild.');

        return self::SUCCESS;
    }
}
