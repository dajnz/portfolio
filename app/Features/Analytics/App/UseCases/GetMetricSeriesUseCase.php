<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Services\MetricSeriesBuilder;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use DateTimeImmutable;

/**
 * The chart series entry point
 * (`GET /admin/analytics/series?metric=&span=`): delegates the dense-window
 * and cumulative computation to `MetricSeriesBuilder`.
 */
final class GetMetricSeriesUseCase
{
    public function __construct(
        private readonly MetricSeriesBuilder $builder,
    ) {
    }

    /**
     * @return array{points: list<array{label: string, value: float}>}
     */
    public function series(
        MetricName $metric,
        MetricSpan $span,
        ?string $dimension = null,
        ?DateTimeImmutable $now = null,
    ): array {
        return $this->builder->series($metric, $span, $dimension, $now);
    }
}
