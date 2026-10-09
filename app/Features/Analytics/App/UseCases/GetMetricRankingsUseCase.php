<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;

/**
 * The top/bottom/liked/disliked ranking payload for
 * `GET /admin/analytics/rankings?metric=&variant=&scope=`: the
 * `span = 'all'` dimension rows of the metric, capped at 10. The
 * `top`/`bottom` variants order by value desc/asc; the
 * `liked`/`disliked` variants rank the reactions metrics desc (the
 * request validates their pairing). The `scope` selects the dimension
 * family (`article:`/`project:` — the `views`/`unique_visitors`
 * metrics span both; `page` = any dimension, the time-on-page URLs).
 * Rows carry the joined data: the EN title for the entity dimensions
 * (rows without a translation are dropped) and the min/max of the
 * avg-able metrics (time-on-page/read-time/completion).
 */
final class GetMetricRankingsUseCase
{
    private const ROW_LIMIT = 10;

    public function __construct(
        private readonly AnalyticsReportRepositoryInterface $reports,
        private readonly AnalyticsReportDataReaderInterface $dataReader,
    ) {
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function rankings(MetricName $metric, string $variant, string $scope): array
    {
        $rows = $this->reports->rankings(
            metric: $metric,
            direction: $this->direction($variant),
            limit: self::ROW_LIMIT,
            dimensionPrefix: $this->dimensionPrefix($scope),
        );

        return ['rows' => $this->rowPayloads($metric, $scope, $rows)];
    }

    private function direction(string $variant): string
    {
        return match ($variant) {
            'top', 'bottom' => $variant,
            default => 'top',
        };
    }

    private function dimensionPrefix(string $scope): ?string
    {
        return match ($scope) {
            'article' => 'article:',
            'project' => 'project:',
            default => null,
        };
    }

    /**
     * @param list<AnalyticsReport> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function rowPayloads(MetricName $metric, string $scope, array $rows): array
    {
        return match ($scope) {
            'page' => $this->pageRows(
                $rows,
                $this->valuesByDimension(MetricName::TimeOnPageMin),
                $this->valuesByDimension(MetricName::TimeOnPageMax),
            ),
            'article' => $this->articleRows($metric, $rows),
            'project' => $this->titledRows(
                $rows,
                'project:',
                $this->dataReader->projectTitles($this->dimensionIds($rows, 'project:')),
            ),
        };
    }

    /**
     * @param list<AnalyticsReport> $rows
     * @param array<string, float> $minByDimension
     * @param array<string, float> $maxByDimension
     *
     * @return list<array{page_url: string, avg: float, min: float, max: float}>
     */
    private function pageRows(array $rows, array $minByDimension, array $maxByDimension): array
    {
        return array_map(
            static fn (AnalyticsReport $row): array => [
                'page_url' => (string) $row->toDimension(),
                'avg' => $row->toValue(),
                'min' => $minByDimension[(string) $row->toDimension()] ?? 0.0,
                'max' => $maxByDimension[(string) $row->toDimension()] ?? 0.0,
            ],
            $rows,
        );
    }

    /**
     * @param list<AnalyticsReport> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function articleRows(MetricName $metric, array $rows): array
    {
        $minByDimension = $this->valuesByDimension($this->avgPartner($metric, 'min'));
        $maxByDimension = $this->valuesByDimension($this->avgPartner($metric, 'max'));

        return $this->titledRows(
            $rows,
            'article:',
            $this->dataReader->articleTitles($this->dimensionIds($rows, 'article:')),
            $minByDimension,
            $maxByDimension,
        );
    }

    /**
     * @param list<AnalyticsReport> $rows
     * @param array<int, string> $titles
     * @param array<string, float> $minByDimension
     * @param array<string, float> $maxByDimension
     *
     * @return list<array<string, mixed>>
     */
    private function titledRows(
        array $rows,
        string $dimensionPrefix,
        array $titles,
        array $minByDimension = [],
        array $maxByDimension = [],
    ): array {
        $payloads = [];

        foreach ($rows as $row) {
            $dimension = (string) $row->toDimension();
            $id = (int) substr($dimension, strlen($dimensionPrefix));

            if (! isset($titles[$id])) {
                continue;
            }

            $payload = [
                'id' => $id,
                'title' => $titles[$id],
                'value' => $row->toValue(),
            ];

            if ($minByDimension !== []) {
                $payload['avg'] = $row->toValue();
                $payload['min'] = $minByDimension[$dimension] ?? 0.0;
                $payload['max'] = $maxByDimension[$dimension] ?? 0.0;
                unset($payload['value']);
            }

            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * The `*_min`/`*_max` partner of the avg-able ranking metrics (the
     * `*_seconds` / `completion_rate_sum` + their min/max columns); the
     * count-like metrics carry no min/max.
     */
    private function avgPartner(MetricName $metric, string $kind): ?MetricName
    {
        $partners = match ($metric) {
            MetricName::ReadTimeSeconds => [MetricName::ReadTimeMin, MetricName::ReadTimeMax],
            MetricName::CompletionRateSum => [MetricName::CompletionRateMin, MetricName::CompletionRateMax],
            default => [],
        };

        return $partners === [] ? null : ($kind === 'min' ? $partners[0] : $partners[1]);
    }

    /**
     * The whole-time values of the given metric keyed by dimension —
     * the min/max join source for the ranked dimensions.
     *
     * @return array<string, float>
     */
    private function valuesByDimension(?MetricName $metric): array
    {
        if ($metric === null) {
            return [];
        }

        $values = [];

        foreach ($this->reports->dimensionRows($metric) as $row) {
            $values[(string) $row->toDimension()] = $row->toValue();
        }

        return $values;
    }

    /**
     * @param list<AnalyticsReport> $rows
     *
     * @return list<int>
     */
    private function dimensionIds(array $rows, string $dimensionPrefix): array
    {
        return array_map(
            static fn (AnalyticsReport $row): int => (int) substr((string) $row->toDimension(), strlen($dimensionPrefix)),
            $rows,
        );
    }
}
