<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use App\Features\Shared\Domain\BaseEntity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Base class for the typed analytics reports (analytics.md:174-179,
 * B288/B289): one entity per report type, each exposing its actual
 * results as a map of typed result values — `ReportScalarValue`
 * (scalar with an optional previous-period value), `ReportSeriesValue`
 * (chart points) or `ReportTableValue` (tabular). The report-specific
 * aggregation is encapsulated in the subclasses' constructors (a
 * report instance is created directly from the period's events), so
 * adding a new report type means a new entity, nothing else; the
 * helpers shared by the report classes (averages, previous-period
 * scalars) live here.
 *
 * The results are the domain representation; the storage/mail/UI
 * serialization happens at the persistence/transport boundary via the
 * result values' `toArray()`.
 */
abstract class AnalyticsReport extends BaseEntity
{
    /** @param array<string, ReportScalarValue|ReportSeriesValue|ReportTableValue> $results */
    public function __construct(
        private readonly int $id,
        private readonly ReportPeriod $period,
        private readonly array $results,
        private readonly DateTimeImmutable $generatedAt,
    ) {
        if ($results === []) {
            throw new InvalidArgumentException('Report results must not be empty.');
        }

        foreach ($results as $result) {
            if (! $result instanceof ReportScalarValue
                && ! $result instanceof ReportSeriesValue
                && ! $result instanceof ReportTableValue) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Report result [%s] must be a ReportScalarValue, ReportSeriesValue or ReportTableValue.',
                        $result::class,
                    ),
                );
            }
        }
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function reportType(): string
    {
        return static::REPORT_TYPE;
    }

    public function toPeriod(): ReportPeriod
    {
        return $this->period;
    }

    public function toPeriodStart(): DateTimeImmutable
    {
        return $this->period->toPeriodStart();
    }

    public function toPeriodEnd(): DateTimeImmutable
    {
        return $this->period->toPeriodEnd();
    }

    /**
     * @return array<string, ReportScalarValue|ReportSeriesValue|ReportTableValue>
     */
    public function toResults(): array
    {
        return $this->results;
    }

    /** The named scalar result, or null when the key is missing or not a scalar. */
    public function toScalar(string $key): ?ReportScalarValue
    {
        $result = $this->results[$key] ?? null;

        return $result instanceof ReportScalarValue
            ? $result
            : null;
    }

    public function toGeneratedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /**
     * The previous-period value of the given scalar key (used as the
     * `previous` of the current period's scalar) — null when the
     * previous report is missing or does not expose the key as a
     * scalar.
     */
    protected function previousScalarValue(?AnalyticsReport $previous, string $key): int|float|null
    {
        return $previous?->toScalar($key)?->toValue();
    }

    /** The rounded average of the samples; null without samples. */
    protected function average(int|float $total, int $samples): ?float
    {
        return $samples > 0
            ? round($total / $samples, 1)
            : null;
    }
}
