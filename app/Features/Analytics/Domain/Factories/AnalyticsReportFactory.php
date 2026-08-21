<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Factories;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Entities\BlogReport;
use App\Features\Analytics\Domain\Entities\FeedbackReport;
use App\Features\Analytics\Domain\Entities\OverallReport;
use App\Features\Analytics\Domain\Entities\ProjectsReport;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * Materializes the stored `analytics_reports` rows (the serialized
 * results payload) into the typed report entities — the single
 * dispatch table for the report types (B288). Returns null for unknown
 * report types (forward compatibility).
 */
final class AnalyticsReportFactory
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function fromRow(
        int $id,
        string $reportType,
        ReportPeriod $period,
        array $payload,
        DateTimeImmutable $generatedAt,
    ): ?AnalyticsReport {
        return match ($reportType) {
            OverallReport::REPORT_TYPE => OverallReport::fromPayload($id, $period, $payload, $generatedAt),
            BlogReport::REPORT_TYPE => BlogReport::fromPayload($id, $period, $payload, $generatedAt),
            ProjectsReport::REPORT_TYPE => ProjectsReport::fromPayload($id, $period, $payload, $generatedAt),
            FeedbackReport::REPORT_TYPE => FeedbackReport::fromPayload($id, $period, $payload, $generatedAt),
            default => null,
        };
    }
}
