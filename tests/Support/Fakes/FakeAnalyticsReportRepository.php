<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;

/**
 * In-memory `AnalyticsReportRepositoryInterface` double: records the
 * replaced/upserted rows (the rebuild suite) and serves the dashboard
 * read shapes from the seeded rows (the 11.3.4 use-case suites) —
 * blocks, last-N series (with the optional dimension filter), the
 * top/bottom rankings (with the optional dimension-prefix filter), the
 * whole-time dimension rows and the cumulative day fold.
 */
final class FakeAnalyticsReportRepository implements AnalyticsReportRepositoryInterface
{
    /** @var list<AnalyticsReport> */
    public array $replaced = [];

    /** @var list<AnalyticsReport> */
    public array $upserted = [];

    public int $replaceAllCalls = 0;

    public int $deletedAllCalls = 0;

    /** @var list<AnalyticsReport> */
    private array $rows;

    /** @param list<AnalyticsReport> $rows */
    public function __construct(
        array $rows = [],
        public readonly int $deletedAllCount = 0,
    ) {
        $this->rows = $rows;
    }

    public function deleteAll(): int
    {
        $this->deletedAllCalls++;

        return $this->deletedAllCount;
    }

    public function replaceAll(array $reports): void
    {
        $this->replaceAllCalls++;
        $this->replaced = $reports;
    }

    public function upsertBatch(array $reports): void
    {
        $this->upserted = array_merge($this->upserted, $reports);
    }

    public function wholeTimeScalarValues(array $metricNames): array
    {
        return $this->matching(
            static fn (AnalyticsReport $row): bool => in_array($row->toMetric(), $metricNames, true)
                && $row->toSpan() === MetricSpan::All
                && $row->toDimension() === null,
        );
    }

    public function series(MetricName $metric, MetricSpan $span, int $limit, ?string $dimension = null): array
    {
        $rows = $this->matching(
            static fn (AnalyticsReport $row): bool => $row->toMetric() === $metric
                && $row->toSpan() === $span
                && $row->toDimension() === $dimension,
        );

        usort(
            $rows,
            static fn (AnalyticsReport $a, AnalyticsReport $b): int => $b->toPeriodStart() <=> $a->toPeriodStart(),
        );

        return array_slice($rows, 0, $limit);
    }

    public function rankings(MetricName $metric, string $direction, int $limit, ?string $dimensionPrefix = null): array
    {
        $rows = $this->dimensionRows($metric, $dimensionPrefix);

        usort(
            $rows,
            static fn (AnalyticsReport $a, AnalyticsReport $b): int => $a->toValue() <=> $b->toValue(),
        );

        if ($direction !== 'bottom') {
            $rows = array_reverse($rows);
        }

        return array_slice($rows, 0, $limit);
    }

    public function metricSpans(MetricName $metric): array
    {
        $present = array_map(
            static fn (AnalyticsReport $row): MetricSpan => $row->toSpan(),
            $this->matching(static fn (AnalyticsReport $row): bool => $row->toMetric() === $metric),
        );

        return array_values(array_filter(
            MetricSpan::cases(),
            static fn (MetricSpan $span): bool => in_array($span, $present, true),
        ));
    }

    public function cumulativeSeries(MetricName $metric, ?string $dimension = null): array
    {
        $rows = $this->matching(
            static fn (AnalyticsReport $row): bool => $row->toMetric() === $metric
                && $row->toSpan() === MetricSpan::Day
                && $row->toDimension() === $dimension,
        );

        usort(
            $rows,
            static fn (AnalyticsReport $a, AnalyticsReport $b): int => $a->toPeriodStart() <=> $b->toPeriodStart(),
        );

        $runningTotal = 0.0;

        return array_map(
            static function (AnalyticsReport $row) use (&$runningTotal): AnalyticsReport {
                $runningTotal += $row->toValue();

                return new AnalyticsReport(
                    id: $row->toId(),
                    metric: $row->toMetric(),
                    span: $row->toSpan(),
                    dimension: $row->toDimension(),
                    periodStart: $row->toPeriodStart(),
                    periodEnd: $row->toPeriodEnd(),
                    value: $runningTotal,
                    createdAt: $row->toCreatedAt(),
                );
            },
            $rows,
        );
    }

    public function dimensionRows(MetricName $metric, ?string $dimensionPrefix = null): array
    {
        $rows = $this->matching(
            static fn (AnalyticsReport $row): bool => $row->toMetric() === $metric
                && $row->toSpan() === MetricSpan::All
                && $row->toDimension() !== null
                && ($dimensionPrefix === null || str_starts_with((string) $row->toDimension(), $dimensionPrefix)),
        );

        usort(
            $rows,
            static fn (AnalyticsReport $a, AnalyticsReport $b): int => (string) $a->toDimension() <=> (string) $b->toDimension(),
        );

        return $rows;
    }

    /**
     * @param callable(AnalyticsReport): bool $filter
     *
     * @return list<AnalyticsReport>
     */
    private function matching(callable $filter): array
    {
        return array_values(array_filter($this->rows, $filter));
    }
}
