<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\RepositoryInterfaces;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;

/**
 * Unified persistence for every report type (B288): the storage is the
 * same regardless of the typed results inside — one row per
 * `(report_type, period_start, period_end)` with the serialized
 * results as jsonb. One repository for all report types — adding a
 * report type adds only its entity (the factory dispatches it).
 */
interface AnalyticsReportRepositoryInterface
{
    public function save(AnalyticsReport $report): void;

    /** @return list<AnalyticsReport> newest period first, report type ascending */
    public function all(): array;

    /**
     * The newest stored report of the type from a period before the
     * given one — the previous-period source for the KPI `previous`
     * values; null when none exists.
     */
    public function findNewestBefore(string $reportType, ReportPeriod $period): ?AnalyticsReport;
}
