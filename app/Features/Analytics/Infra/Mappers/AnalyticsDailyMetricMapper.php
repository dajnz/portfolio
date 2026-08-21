<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Mappers;

use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Infra\Models\AnalyticsDailyMetricModel;

/**
 * Converts between `analytics_daily_metrics` rows and the
 * `AnalyticsMetric` entity: `toRow` serializes the entity back into
 * the stored row (`created_at` from the entity, the DB default is a
 * safety net), `toEntity` parses a model row back into the entity.
 */
final class AnalyticsDailyMetricMapper
{
    /**
     * @return array{
     *     metric: string,
     *     date: string,
     *     dimension: ?string,
     *     value: float,
     *     samples: int,
     *     created_at: \DateTimeImmutable,
     * }
     */
    public function toRow(AnalyticsMetric $metric): array
    {
        return [
            'metric' => $metric->toMetric()->value,
            'date' => $metric->toDate()->format('Y-m-d'),
            'dimension' => $metric->toDimension(),
            'value' => $metric->toValue(),
            'samples' => $metric->toSamples(),
            'created_at' => $metric->toCreatedAt(),
        ];
    }

    public function toEntity(AnalyticsDailyMetricModel $model): AnalyticsMetric
    {
        return new AnalyticsMetric(
            id: $model->id,
            metric: MetricName::from($model->metric),
            date: $model->date->toDateTimeImmutable(),
            dimension: $model->dimension,
            value: $model->value,
            samples: $model->samples,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
