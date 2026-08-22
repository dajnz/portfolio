<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use DateTimeImmutable;

/**
 * The 90-day raw-event retention safety net (analytics.md:245-247):
 * deletes `analytics_events` rows older than the cutoff. Runs via the
 * monthly `analytics:prune-events` command only (B37 — the fixed cutoff
 * constant lives here); events may age up to ~120 days before the
 * monthly sweep removes them.
 */
final class PruneEventsUseCase
{
    public const EVENT_RETENTION_DAYS = 90;
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
