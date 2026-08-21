<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Commands;

use App\Features\Analytics\App\UseCases\PruneEventsUseCase;
use App\Features\Analytics\Infra\Jobs\AggregateAnalyticsReportsJob;
use Illuminate\Console\Command;

/**
 * `analytics:prune-events` (analytics.md:216, backup.md:464) — the
 * monthly safety net that applies the 90-day retention independently of
 * report generation, so raw events never exceed the window even when
 * aggregation is skipped or fails.
 */
final class PruneAnalyticsEventsCommand extends Command
{
    protected $signature = 'analytics:prune-events';

    protected $description = 'Delete raw analytics events older than 90 days';

    public function handle(PruneEventsUseCase $pruneEvents): int
    {
        $deleted = $pruneEvents->prune(AggregateAnalyticsReportsJob::EVENT_RETENTION_DAYS);

        $this->info(
            sprintf(
                'Deleted %d analytics event(s) older than %d days.',
                $deleted,
                AggregateAnalyticsReportsJob::EVENT_RETENTION_DAYS,
            ),
        );

        return self::SUCCESS;
    }
}
