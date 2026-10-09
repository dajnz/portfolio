<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Repositories;

use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Infra\Mappers\AnalyticsDailyMetricMapper;
use App\Features\Analytics\Infra\Models\AnalyticsDailyMetricModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed `analytics_daily_metrics` storage (ADR-0017). Writes go
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

    public function deleteAll(): int
    {
        return DB::table('analytics_daily_metrics')->delete();
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

    public function aggregateForSpan(MetricSpan $span): array
    {
        [$bucket, $startExpression, $endExpression] = $this->spanExpressions($span);

        $query = DB::table('analytics_daily_metrics')
            ->select([
                'metric',
                'dimension',
                DB::raw("{$startExpression} as period_start"),
                DB::raw("{$endExpression} as period_end"),
                DB::raw('sum(value) as value_sum'),
                DB::raw('sum(samples) as samples_sum'),
                DB::raw('min(value) as value_min'),
                DB::raw('max(value) as value_max'),
            ]);

        if ($span === MetricSpan::All) {
            $query->groupBy('metric', 'dimension');
        } else {
            // PostgreSQL requires every derived select expression in
            // the GROUP BY clause (no functional-dependency inference
            // for expressions — only the bucket would group by the
            // calendar start; the period-end expression is a pure
            // function of the same dates, so it never splits a group).
            $query->groupBy('metric', 'dimension', DB::raw($bucket));

            if ($endExpression !== $bucket) {
                $query->groupBy(DB::raw($endExpression));
            }
        }

        return $query
            ->orderBy('metric')
            ->orderBy('dimension')
            ->orderBy('period_start')
            ->get()
            ->map(static function (mixed $row): array {
                return [
                    'metric' => (string) $row->metric,
                    'dimension' => $row->dimension,
                    'period_start' => new DateTimeImmutable((string) $row->period_start),
                    'period_end' => new DateTimeImmutable((string) $row->period_end),
                    'value_sum' => (float) $row->value_sum,
                    'samples_sum' => (int) $row->samples_sum,
                    'value_min' => (float) $row->value_min,
                    'value_max' => (float) $row->value_max,
                ];
            })
            ->all();
    }

    /**
     * The per-span SQL expressions (analytics.md:202): the GROUP BY
     * bucket and the calendar period bounds — `week` = Monday–Sunday
     * (PostgreSQL `date_trunc('week', ...)` starts on Monday), the
     * month/year rows span their calendar bounds, `all` spans the
     * stored history of the group.
     *
     * @return array{string, string, string}
     */
    private function spanExpressions(MetricSpan $span): array
    {
        return match ($span) {
            MetricSpan::Day => ['date', 'date', 'date'],
            MetricSpan::Week => [
                "date_trunc('week', date)::date",
                "date_trunc('week', date)::date",
                "(date_trunc('week', date) + interval '6 days')::date",
            ],
            MetricSpan::Month => [
                "date_trunc('month', date)::date",
                "date_trunc('month', date)::date",
                "(date_trunc('month', date) + interval '1 month' - interval '1 day')::date",
            ],
            MetricSpan::Year => [
                "date_trunc('year', date)::date",
                "date_trunc('year', date)::date",
                "(date_trunc('year', date) + interval '1 year' - interval '1 day')::date",
            ],
            MetricSpan::All => ['null', 'min(date)', 'max(date)'],
        };
    }
}
