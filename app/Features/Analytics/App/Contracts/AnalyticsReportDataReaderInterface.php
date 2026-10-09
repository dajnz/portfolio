<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\Contracts;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * The cross-feature data the analytics pipeline needs (ADR-0003/ADR-0017): the
 * feedback submission timestamps (the `feedback_submissions` metric —
 * feedback rows are never pruned, so the metric can be rebuilt for the
 * full history) and the EN titles of the ranking dimensions (11.3.4 —
 * the `article:{id}`/`project:{id}` metric rows carry the entity IDs
 * only; the dashboard rankings join the titles at query time).
 * Implemented on the Infra layer; the use cases depend on this
 * interface only.
 */
interface AnalyticsReportDataReaderInterface
{
    /** @return list<DateTimeImmutable> */
    public function feedbackSubmissions(ReportPeriod $period): array;

    /**
     * The calendar date (server-local timezone) of the oldest feedback
     * submission — the start of the full-rebuild feedback recompute
     * (feedback rows are never pruned, so `feedback_submissions` can be
     * re-derived for the full history, analytics.md:209). Null when no
     * feedback rows exist.
     */
    public function oldestFeedbackDate(): ?DateTimeImmutable;

    /**
     * The EN titles of the given article ids, keyed by id (the
     * rankings' display names). Articles without an EN translation are
     * absent — the rankings drop their rows.
     *
     * @param list<int> $articleIds
     *
     * @return array<int, string>
     */
    public function articleTitles(array $articleIds): array;

    /**
     * The EN titles of the given project ids, keyed by id (the
     * rankings' display names). Projects without an EN translation are
     * absent — the rankings drop their rows.
     *
     * @param list<int> $projectIds
     *
     * @return array<int, string>
     */
    public function projectTitles(array $projectIds): array;
}
