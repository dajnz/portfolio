<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use DateTimeImmutable;

/**
 * The 90-day raw-event retention safety net (analytics.md:214-216):
 * deletes `analytics_events` rows older than the cutoff. Called by
 * `AggregateAnalyticsReportsJob` after successful aggregation and by the
 * monthly `analytics:prune-events` command.
 */
final class PruneEventsUseCase
{
    public function __construct(
        private readonly AnalyticsEventRepositoryInterface $repository,
    ) {
    }

    /** @return int deleted row count */
    public function prune(int $retentionDays, ?DateTimeImmutable $now = null): int
    {
        $cutoff = ($now ?? new DateTimeImmutable())->modify("-{$retentionDays} days");

        return $this->repository->deleteOlderThan($cutoff);
    }
}
