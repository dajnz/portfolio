<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;

/**
 * The admin analytics dashboard (analytics.md:196-202, inertia
 * `analytics.yaml`): every stored report from the unified repository,
 * newest period first (report type ascending within a period) — the
 * frontend groups them by report type and renders the serialized
 * typed results (scalar cards, series charts, tables).
 */
final class GetAnalyticsDashboardUseCase
{
    public function __construct(
        private readonly AnalyticsReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @return list<array{report_type: string, period_start: string, period_end: string, data: array}>
     */
    public function get(): array
    {
        return array_map(static function (AnalyticsReport $report): array {
            return [
                'report_type' => $report->reportType(),
                'period_start' => $report->toPeriodStart()->format('Y-m-d'),
                'period_end' => $report->toPeriodEnd()->format('Y-m-d'),
                'data' => array_map(
                    static fn (ReportScalarValue|ReportSeriesValue|ReportTableValue $result): array => $result->toArray(),
                    $report->toResults(),
                ),
            ];
        }, $this->reports->all());
    }
}
