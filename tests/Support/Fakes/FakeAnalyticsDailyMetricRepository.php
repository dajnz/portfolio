<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use DateTimeImmutable;

/**
 * In-memory `AnalyticsDailyMetricRepositoryInterface` double for the
 * aggregation + report-rebuild unit suites: records the upserted rows
 * and the deleted date ranges, returns the canned per-span group rows.
 */
final class FakeAnalyticsDailyMetricRepository implements AnalyticsDailyMetricRepositoryInterface
{
    /** @var list<AnalyticsMetric> */
    public array $stored = [];

    /** @var list<array{DateTimeImmutable, DateTimeImmutable}> */
    public array $deletedRanges = [];

    /** @var array<string, list<array<string, mixed>>> */
    public array $groups = [];

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
        $this->deletedRanges[] = [$dateFrom, $dateTo];

        return 0;
    }

    public function datesCovered(): array
    {
        return [];
    }

    public function aggregateForSpan(MetricSpan $span): array
    {
        return $this->groups[$span->value] ?? [];
    }
}
