<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Mappers;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Infra\Models\AnalyticsReportModel;

/**
 * Converts between `analytics_reports` rows and the `AnalyticsReport`
 * entity: `toRow` serializes the entity back into the stored row
 * (`created_at` from the entity, the DB default is a safety net),
 * `toEntity` materializes a model row through the entity's `fromRow()`
 * (the single row-to-entity path).
 */
final class AnalyticsReportMapper
{
    /**
     * @return array{
     *     metric: string,
     *     span: string,
     *     dimension: ?string,
     *     period_start: string,
     *     period_end: string,
     *     value: float,
     *     created_at: \DateTimeImmutable,
     * }
     */
    public function toRow(AnalyticsReport $report): array
    {
        return [
            'metric' => $report->toMetric()->value,
            'span' => $report->toSpan()->value,
            'dimension' => $report->toDimension(),
            'period_start' => $report->toPeriodStart()->format('Y-m-d'),
            'period_end' => $report->toPeriodEnd()->format('Y-m-d'),
            'value' => $report->toValue(),
            'created_at' => $report->toCreatedAt(),
        ];
    }

    public function toEntity(AnalyticsReportModel $model): AnalyticsReport
    {
        return AnalyticsReport::fromRow($model->getAttributes());
    }
}
