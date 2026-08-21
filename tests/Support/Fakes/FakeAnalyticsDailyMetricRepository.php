<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use DateTimeImmutable;

/**
 * In-memory `AnalyticsDailyMetricRepositoryInterface` double for the
 * aggregation unit suite: records the upserted rows (the read/delete
 * methods are unused there).
 */
final class FakeAnalyticsDailyMetricRepository implements AnalyticsDailyMetricRepositoryInterface
{
    /** @var list<AnalyticsMetric> */
    public array $stored = [];

    public function upsertBatch(array $metrics): void
    {
        $this->stored = array_merge($this->stored, $metrics);
    }

    public function rowsBetween(
        ?MetricName $metric,
        DateTimeImmutable $dateFrom,
        DateTimeImmutable $dateTo,
        ?string $dimension = null,
    ): array {
        return [];
    }

    public function deleteForDates(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): int
    {
        return 0;
    }

    public function datesCovered(): array
    {
        return [];
    }
}
