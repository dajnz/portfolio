<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Analytics\Domain\Entities\AnalyticsEvent;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * In-memory `AnalyticsEventRepositoryInterface` double for the unit
 * suite: records the inserted batch and the prune cutoffs, returns the
 * configured period events.
 */
final class FakeAnalyticsEventRepository implements AnalyticsEventRepositoryInterface
{
    /** @var list<AnalyticsEvent> */
    public array $insertedEvents = [];

    /** @var list<DateTimeImmutable> */
    public array $deletedCutoffs = [];

    /** @param list<AnalyticsEvent> $periodEvents */
    public function __construct(
        public readonly array $periodEvents = [],
        public readonly int $deletedCount = 0,
    ) {
    }

    public function insertBatch(array $events): void
    {
        $this->insertedEvents = array_merge($this->insertedEvents, $events);
    }

    public function eventsBetween(ReportPeriod $period): array
    {
        return $this->periodEvents;
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        $this->deletedCutoffs[] = $cutoff;

        return $this->deletedCount;
    }
}
