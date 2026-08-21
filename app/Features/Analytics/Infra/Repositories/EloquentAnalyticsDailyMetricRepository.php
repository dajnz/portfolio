<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Repositories;

use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Infra\Mappers\AnalyticsDailyMetricMapper;
use App\Features\Analytics\Infra\Models\AnalyticsDailyMetricModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed `analytics_daily_metrics` storage (B290). Writes go
 * through the query builder — `Eloquent\Builder::upsert` does not
 * apply the model's casts, so the rows are prepared by the mapper
 * (dates as `Y-m-d` strings, floats/ints as scalars) and upserted on
 * the `(metric, date, dimension)` unique key (NULLS NOT DISTINCT —
 * the NULL dimension row conflicts like any other). Reads materialize
 * the rows through the mapper.
 */
final class EloquentAnalyticsDailyMetricRepository implements AnalyticsDailyMetricRepositoryInterface
{
    public function __construct(
        private readonly AnalyticsDailyMetricMapper $mapper,
    ) {
    }

    public function upsertBatch(array $metrics): void
    {
        if ($metrics === []) {
            return;
        }

        $rows = array_map(fn (AnalyticsMetric $metric): array => $this->mapper->toRow($metric), $metrics);

        DB::table('analytics_daily_metrics')->upsert(
            $rows,
            ['metric', 'date', 'dimension'],
            ['value', 'samples'],
        );
    }

    public function rowsBetween(
        ?MetricName $metric,
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        ?string $dimension = null,
    ): array {
        $query = AnalyticsDailyMetricModel::query()
            ->whereBetween('date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')]);

        if ($metric !== null) {
            $query->where('metric', $metric->value);
        }

        if ($dimension !== null) {
            $query->where('dimension', $dimension);
        }

        return $query
            ->orderBy('date')
            ->orderBy('metric')
            ->orderBy('dimension')
            ->get()
            ->map(fn (AnalyticsDailyMetricModel $model): AnalyticsMetric => $this->mapper->toEntity($model))
            ->all();
    }

    public function deleteForDates(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): int
    {
        return DB::table('analytics_daily_metrics')
            ->whereBetween('date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
            ->delete();
    }

    public function datesCovered(): array
    {
        return AnalyticsDailyMetricModel::query()
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->map(fn (mixed $date): DateTimeImmutable => new DateTimeImmutable((string) $date))
            ->all();
    }
}
