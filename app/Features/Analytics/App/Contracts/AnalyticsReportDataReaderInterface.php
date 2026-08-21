<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\Contracts;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * The cross-feature data the analytics pipeline needs (B11/B292): the
 * feedback submission timestamps (the `feedback_submissions` metric —
 * feedback rows are never pruned, so the metric can be rebuilt for the
 * full history). The per-article/per-project metric rows carry the
 * entity IDs only (`article:{id}`/`project:{id}`); the titles are
 * joined at query time by the dashboard/rankings layer (11.3.4).
 * Implemented on the Infra layer; the use cases depend on this
 * interface only.
 */
interface AnalyticsReportDataReaderInterface
{
    /** @return list<DateTimeImmutable> */
    public function feedbackSubmissions(ReportPeriod $period): array;
}
