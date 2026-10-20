<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Services\MetricRankingsBuilder;
use App\Features\Analytics\Domain\Enums\MetricName;

/**
 * The rankings entry point
 * (`GET /admin/analytics/rankings?metric=&variant=&scope=`): delegates the
 * row building to `MetricRankingsBuilder`.
 */
final class GetMetricRankingsUseCase
{
    public function __construct(
        private readonly MetricRankingsBuilder $builder,
    ) {
    }

    /**
     * @return array{rows: list<array<string, mixed>>}
     */
    public function rankings(MetricName $metric, string $variant, string $scope): array
    {
        return $this->builder->rankings($metric, $variant, $scope);
    }
}
