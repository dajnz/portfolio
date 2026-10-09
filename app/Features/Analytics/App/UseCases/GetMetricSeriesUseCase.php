<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;

/**
 * The chart series payload (analytics.md:225, ADR-0017, 11.3.4) for
 * `GET /admin/analytics/series?metric=&span=`: the last 90
 * dimension-NULL rows of the span, chronological with `period_start`
 * date labels; `span=all` returns the cumulative running total over
 * the day rows (the "whole time" chart source). The optional dimension
 * reads the per-target `cta_clicks` rows — folded into the cumulative
 * at `span=all` (the CTA multi-chart fetches one per-target series for
 * every span, incl. whole-time, 11.3.7).
 */
final class GetMetricSeriesUseCase
{
    private const POINT_LIMIT = 90;

    public function __construct(
        private readonly AnalyticsReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @return array{points: list<array{label: string, value: float}>}
     */
    public function series(MetricName $metric, MetricSpan $span, ?string $dimension = null): array
    {
        if ($span === MetricSpan::All) {
            return ['points' => $this->points($this->reports->cumulativeSeries($metric, $dimension))];
        }

        $rows = $this->reports->series(
            metric: $metric,
            span: $span,
            limit: self::POINT_LIMIT,
            dimension: $dimension,
        );

        return ['points' => $this->points(array_reverse($rows))];
    }

    /**
     * @param list<AnalyticsReport> $rows
     *
     * @return list<array{label: string, value: float}>
     */
    private function points(array $rows): array
    {
        return array_map(
            static fn (AnalyticsReport $row): array => [
                'label' => $row->toPeriodStart()->format('Y-m-d'),
                'value' => $row->toValue(),
            ],
            $rows,
        );
    }
}
