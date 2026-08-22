<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\RepositoryInterfaces;

use App\Features\Analytics\Domain\Entities\AnalyticsEvent;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * Raw event storage. Events are write-mostly (B287): one repository
 * for all typed event classes — the type is carried by the entity
 * itself, so per-type repositories would be empty shells. The period
 * read materializes the stored rows back into the typed entities (the
 * aggregation runs in the report entities, not in SQL).
 */
interface AnalyticsEventRepositoryInterface
{
    /** @param list<AnalyticsEvent> $events */
    public function insertBatch(array $events): void;

    /**
     * All events of the period as typed entities (unknown/malformed
     * rows are skipped by the parser).
     *
     * @return list<AnalyticsEvent>
     */
    public function eventsBetween(ReportPeriod $period): array;

    /** Deletes events older than the cutoff (90-day retention, B37). */
    public function deleteOlderThan(DateTimeImmutable $cutoff): int;

    /** Deletes every stored event (the `analytics:wipe` reset). */
    public function deleteAll(): int;

    /**
     * The calendar date (server-local timezone) of the oldest stored
     * event — the start of the full-rebuild metric-window recompute
     * (analytics.md:209). Null when no events are stored.
     */
    public function oldestEventDate(): ?DateTimeImmutable;
}
