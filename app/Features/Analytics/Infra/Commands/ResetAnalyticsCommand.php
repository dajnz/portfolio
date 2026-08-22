<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Commands;

use App\Features\Analytics\App\UseCases\ResetAnalyticsUseCase;
use Illuminate\Console\Command;

/**
 * `analytics:wipe` (analytics.md "Data deletion", manual testing aid):
 * deletes ALL raw events, daily metrics and reports — the "fresh
 * state" reset before manual analytics testing. Interactive terminals
 * get a confirmation prompt unless `--force` is given (the
 * ResetAdminPasswordCommand pattern); the command reports the
 * per-table deleted counts.
 */
final class ResetAnalyticsCommand extends Command
{
    protected $signature = 'analytics:wipe {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all analytics events, daily metrics and reports';

    public function handle(ResetAnalyticsUseCase $wipe): int
    {
        if ($this->input->isInteractive() && ! $this->option('force')) {
            if (! $this->confirm('Delete ALL analytics events, daily metrics and reports?', false)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $result = $wipe->wipe();

        $this->info(
            sprintf(
                'Deleted %d event(s), %d metric row(s), %d report row(s).',
                $result->events,
                $result->metrics,
                $result->reports,
            ),
        );

        return self::SUCCESS;
    }
}
