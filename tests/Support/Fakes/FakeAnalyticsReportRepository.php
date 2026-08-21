<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;

/**
 * In-memory `AnalyticsReportRepositoryInterface` double for the report
 * rebuild unit suite: records the replaced/upserted rows (the read
 * methods are unused there).
 */
final class FakeAnalyticsReportRepository implements AnalyticsReportRepositoryInterface
{
    /** @var list<AnalyticsReport> */
    public array $replaced = [];

    /** @var list<AnalyticsReport> */
    public array $upserted = [];

    public int $replaceAllCalls = 0;

    public function replaceAll(array $reports): void
    {
        $this->replaceAllCalls++;
        $this->replaced = $reports;
    }

    public function upsertBatch(array $reports): void
    {
        $this->upserted = array_merge($this->upserted, $reports);
    }

    public function blocks(array $metricNames): array
    {
        return [];
    }

    public function series(MetricName $metric, MetricSpan $span, int $limit): array
    {
        return [];
    }

    public function rankings(MetricName $metric, string $direction, int $limit): array
    {
        return [];
    }

    public function metricSpans(MetricName $metric): array
    {
        return [];
    }

    public function cumulativeSeries(MetricName $metric): array
    {
        return [];
    }
}
