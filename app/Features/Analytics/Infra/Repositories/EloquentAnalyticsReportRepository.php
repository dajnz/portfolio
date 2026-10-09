<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Repositories;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Infra\Mappers\AnalyticsReportMapper;
use App\Features\Analytics\Infra\Models\AnalyticsReportModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Eloquent-backed `analytics_reports` storage (ADR-0017). Writes go
 * through the query builder (the model's casts do not apply there —
 * the mapper prepares the rows: dates as `Y-m-d` strings, floats as
 * scalars) and upsert on the `(metric, span, dimension, period_start,
 * period_end)` unique key (NULLS NOT DISTINCT). Reads materialize the
 * rows through the entity's `fromRow()`.
 */
final class EloquentAnalyticsReportRepository implements AnalyticsReportRepositoryInterface
{
    public function __construct(
        private readonly AnalyticsReportMapper $mapper,
    ) {
    }

    public function deleteAll(): int
    {
        return DB::table('analytics_reports')->delete();
    }

    public function replaceAll(array $reports): void
    {
        DB::transaction(function () use ($reports): void {
            DB::table('analytics_reports')->delete();

            $this->upsertBatch($reports);
        });
    }

    public function upsertBatch(array $reports): void
    {
        if ($reports === []) {
            return;
        }

        $rows = array_map(fn (AnalyticsReport $report): array => $this->mapper->toRow($report), $reports);

        DB::table('analytics_reports')->upsert(
            $rows,
            ['metric', 'span', 'dimension', 'period_start', 'period_end'],
            ['value'],
        );
    }

    public function wholeTimeScalarValues(array $metricNames): array
    {
        return AnalyticsReportModel::query()
            ->whereIn('metric', array_map(fn (MetricName $metric): string => $metric->value, $metricNames))
            ->where('span', MetricSpan::All->value)
            ->whereNull('dimension')
            ->orderBy('metric')
            ->get()
            ->map(fn (AnalyticsReportModel $model): AnalyticsReport => $this->mapper->toEntity($model))
            ->all();
    }

    public function series(MetricName $metric, MetricSpan $span, int $limit, ?string $dimension = null): array
    {
        $query = AnalyticsReportModel::query()
            ->where('metric', $metric->value)
            ->where('span', $span->value)
            ->orderByDesc('period_start');

        if ($dimension === null) {
            $query->whereNull('dimension');
        } else {
            $query->where('dimension', $dimension);
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (AnalyticsReportModel $model): AnalyticsReport => $this->mapper->toEntity($model))
            ->all();
    }

    public function rankings(MetricName $metric, string $direction, int $limit, ?string $dimensionPrefix = null): array
    {
        $order = match ($direction) {
            'top' => 'desc',
            'bottom' => 'asc',
            default => throw new InvalidArgumentException(
                sprintf('Unknown ranking direction [%s].', $direction),
            ),
        };

        return AnalyticsReportModel::query()
            ->where('metric', $metric->value)
            ->where('span', MetricSpan::All->value)
            ->whereNotNull('dimension')
            ->when(
                $dimensionPrefix !== null,
                static fn ($query) => $query->where('dimension', 'like', $dimensionPrefix.'%'),
            )
            ->orderBy('value', $order)
            ->orderBy('dimension')
            ->limit($limit)
            ->get()
            ->map(fn (AnalyticsReportModel $model): AnalyticsReport => $this->mapper->toEntity($model))
            ->all();
    }

    public function dimensionRows(MetricName $metric, ?string $dimensionPrefix = null): array
    {
        return AnalyticsReportModel::query()
            ->where('metric', $metric->value)
            ->where('span', MetricSpan::All->value)
            ->whereNotNull('dimension')
            ->when(
                $dimensionPrefix !== null,
                static fn ($query) => $query->where('dimension', 'like', $dimensionPrefix.'%'),
            )
            ->orderBy('dimension')
            ->get()
            ->map(fn (AnalyticsReportModel $model): AnalyticsReport => $this->mapper->toEntity($model))
            ->all();
    }

    public function metricSpans(MetricName $metric): array
    {
        $present = AnalyticsReportModel::query()
            ->where('metric', $metric->value)
            ->distinct()
            ->pluck('span')
            ->map(fn (mixed $span): MetricSpan => MetricSpan::from((string) $span))
            ->all();

        return array_values(array_filter(
            MetricSpan::cases(),
            static fn (MetricSpan $span): bool => in_array($span, $present, true),
        ));
    }

    public function cumulativeSeries(MetricName $metric, ?string $dimension = null): array
    {
        $query = AnalyticsReportModel::query()
            ->where('metric', $metric->value)
            ->where('span', MetricSpan::Day->value)
            ->orderBy('period_start');

        if ($dimension === null) {
            $query->whereNull('dimension');
        } else {
            $query->where('dimension', $dimension);
        }

        $dayModels = $query->get();

        $runningTotal = 0.0;

        return $dayModels
            ->map(function (AnalyticsReportModel $model) use (&$runningTotal): AnalyticsReport {
                $day = $this->mapper->toEntity($model);
                $runningTotal += $day->toValue();

                return new AnalyticsReport(
                    id: 0,
                    metric: $day->toMetric(),
                    span: $day->toSpan(),
                    dimension: $day->toDimension(),
                    periodStart: $day->toPeriodStart(),
                    periodEnd: $day->toPeriodEnd(),
                    value: $runningTotal,
                    createdAt: $day->toCreatedAt(),
                );
            })
            ->all();
    }
}
