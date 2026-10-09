<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\DTOs\AnalyticsDashboardDto;
use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;

/**
 * The admin analytics dashboard props for `GET /admin/analytics`: the
 * whole-time metric blocks per tab (overview/blog/projects —
 * `{key, value, min?, max?}`, keys are the stable identifiers the
 * frontend maps to i18n labels and to the series/rankings parameters),
 * the default chart series (`page_views`, day/week/month + the
 * cumulative `all`) and the default rankings (time-on-page top/bottom
 * 10 pages). Metrics without stored rows fall back to a zero block.
 */
final class GetAnalyticsDashboardUseCase
{
    private const SERIES_POINT_LIMIT = 90;

    public function __construct(
        private readonly AnalyticsReportRepositoryInterface $reports,
        private readonly GetMetricRankingsUseCase $rankings,
    ) {
    }

    public function get(): AnalyticsDashboardDto
    {
        return new AnalyticsDashboardDto(
            overviewBlocks: $this->overviewBlocks(),
            blogBlocks: $this->blogBlocks(),
            projectsBlocks: $this->projectsBlocks(),
            defaultChart: $this->defaultChart(),
            defaultRankings: $this->defaultRankings(),
        );
    }

    /**
     * @return list<array{key: string, value: float, min?: float, max?: float}>
     */
    private function overviewBlocks(): array
    {
        $values = $this->blockValues([
            MetricName::PageViews,
            MetricName::UniqueVisitors,
            MetricName::FeedbackSubmissions,
            MetricName::TimeOnPageSeconds,
            MetricName::TimeOnPageMin,
            MetricName::TimeOnPageMax,
        ]);
        $ctaTargets = $this->dimensionValues(MetricName::CtaClicks);

        return [
            ['key' => 'total_visits', 'value' => $values[MetricName::PageViews->value] ?? 0.0],
            ['key' => 'unique_visitors', 'value' => $values[MetricName::UniqueVisitors->value] ?? 0.0],
            ['key' => 'cta_clicks', 'value' => (float) array_sum($ctaTargets)],
            ['key' => 'feedback_submissions', 'value' => $values[MetricName::FeedbackSubmissions->value] ?? 0.0],
            ['key' => 'language_switches', 'value' => $ctaTargets['language'] ?? 0.0],
            $this->avgBlock(
                'avg_time_on_page',
                $values,
                MetricName::TimeOnPageSeconds,
                MetricName::TimeOnPageMin,
                MetricName::TimeOnPageMax,
            ),
        ];
    }

    /**
     * @return list<array{key: string, value: float, min?: float, max?: float}>
     */
    private function blogBlocks(): array
    {
        $values = $this->blockValues([
            MetricName::BlogViews,
            MetricName::BlogUniqueVisitors,
            MetricName::ReadTimeSeconds,
            MetricName::ReadTimeMin,
            MetricName::ReadTimeMax,
            MetricName::CompletionRateSum,
            MetricName::CompletionRateMin,
            MetricName::CompletionRateMax,
            MetricName::ReactionsLikes,
            MetricName::ReactionsDislikes,
        ]);

        return [
            ['key' => 'total_views', 'value' => $values[MetricName::BlogViews->value] ?? 0.0],
            ['key' => 'unique_visitors', 'value' => $values[MetricName::BlogUniqueVisitors->value] ?? 0.0],
            $this->avgBlock(
                'avg_read_time',
                $values,
                MetricName::ReadTimeSeconds,
                MetricName::ReadTimeMin,
                MetricName::ReadTimeMax,
            ),
            $this->avgBlock(
                'completion_rate',
                $values,
                MetricName::CompletionRateSum,
                MetricName::CompletionRateMin,
                MetricName::CompletionRateMax,
            ),
            [
                'key' => 'reactions',
                'value' => ($values[MetricName::ReactionsLikes->value] ?? 0.0) + ($values[MetricName::ReactionsDislikes->value] ?? 0.0),
            ],
        ];
    }

    /**
     * The projects blocks — the sums of the per-project dimension rows
     * (the catalog stores no dimension-NULL project totals) and the
     * project-scoped uniques (the distinct visitors across ALL project
     * pages — the same visitor viewing several projects counts once,
     * unlike the per-project rows).
     *
     * @return list<array{key: string, value: float}>
     */
    private function projectsBlocks(): array
    {
        $values = $this->blockValues([MetricName::ProjectUniqueVisitors]);

        return [
            ['key' => 'total_views', 'value' => (float) array_sum($this->dimensionValues(MetricName::Views, 'project:'))],
            ['key' => 'unique_views', 'value' => $values[MetricName::ProjectUniqueVisitors->value] ?? 0.0],
        ];
    }

    /**
     * @return array{metric: string, spans: array<string, array{points: list<array{label: string, value: float}>}>}
     */
    private function defaultChart(): array
    {
        $metric = MetricName::PageViews;

        return [
            'metric' => $metric->value,
            'spans' => [
                'day' => $this->points($this->spanRows($metric, MetricSpan::Day)),
                'week' => $this->points($this->spanRows($metric, MetricSpan::Week)),
                'month' => $this->points($this->spanRows($metric, MetricSpan::Month)),
                'all' => $this->points($this->reports->cumulativeSeries($metric)),
            ],
        ];
    }

    /**
     * @return array{metric: string, scope: string, rows: array<string, list<array<string, mixed>>>}
     */
    private function defaultRankings(): array
    {
        return [
            'metric' => MetricName::TimeOnPageSeconds->value,
            'scope' => 'page',
            'rows' => [
                'top' => $this->rankings->rankings(MetricName::TimeOnPageSeconds, 'top', 'page')['rows'],
                'bottom' => $this->rankings->rankings(MetricName::TimeOnPageSeconds, 'bottom', 'page')['rows'],
            ],
        ];
    }

    /**
     * The whole-time block values of the metrics keyed by metric.
     *
     * @param list<MetricName> $metricNames
     *
     * @return array<string, float>
     */
    private function blockValues(array $metricNames): array
    {
        $values = [];

        foreach ($this->reports->wholeTimeScalarValues($metricNames) as $row) {
            $values[$row->toMetric()->value] = $row->toValue();
        }

        return $values;
    }

    /**
     * The whole-time dimension values of the metric keyed by dimension.
     *
     * @return array<string, float>
     */
    private function dimensionValues(MetricName $metric, ?string $dimensionPrefix = null): array
    {
        $values = [];

        foreach ($this->reports->dimensionRows($metric, $dimensionPrefix) as $row) {
            $values[(string) $row->toDimension()] = $row->toValue();
        }

        return $values;
    }

    /**
     * @param array<string, float> $values
     *
     * @return array{key: string, value: float, min?: float, max?: float}
     */
    private function avgBlock(string $key, array $values, MetricName $avg, MetricName $min, MetricName $max): array
    {
        $block = ['key' => $key, 'value' => $values[$avg->value] ?? 0.0];

        if (isset($values[$avg->value])) {
            $block['min'] = $values[$min->value] ?? 0.0;
            $block['max'] = $values[$max->value] ?? 0.0;
        }

        return $block;
    }

    /**
     * @return list<AnalyticsReport>
     */
    private function spanRows(MetricName $metric, MetricSpan $span): array
    {
        return array_reverse($this->reports->series(
            metric: $metric,
            span: $span,
            limit: self::SERIES_POINT_LIMIT,
        ));
    }

    /**
     * @param list<AnalyticsReport> $rows
     *
     * @return array{points: list<array{label: string, value: float}>}
     */
    private function points(array $rows): array
    {
        return [
            'points' => array_map(
                static fn (AnalyticsReport $row): array => [
                    'label' => $row->toPeriodStart()->format('Y-m-d'),
                    'value' => $row->toValue(),
                ],
                $rows,
            ),
        ];
    }
}
