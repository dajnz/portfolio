<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Shared\Domain\BaseEntity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One row of `analytics_reports`: a per-metric aggregate over the daily
 * metrics, pre-computed for one span. NULL dimension = block/chart
 * series row (`span = 'all'` for the whole-time blocks, the last-N
 * rows of the span for the series); otherwise the entity key —
 * `span = 'all'` + dimension rows power the rankings.
 *
 * The constructor protects the invariants: the period bounds are
 * ordered, the value is finite, and the dimension must fit the
 * `varchar(500)` column. `fromRow()` materializes a stored row (the
 * single row-to-entity path — used by the mapper); unknown metric or
 * span names throw (the storage is written by the aggregation only,
 * so an unknown name is corrupt data).
 */
final class AnalyticsReport extends BaseEntity
{
    public const MAX_DIMENSION_LENGTH = 500;

    public function __construct(
        private readonly int $id,
        private readonly MetricName $metric,
        private readonly MetricSpan $span,
        private readonly ?string $dimension,
        private readonly DateTimeImmutable $periodStart,
        private readonly DateTimeImmutable $periodEnd,
        private readonly float $value,
        private readonly DateTimeImmutable $createdAt,
    ) {
        if ($periodStart > $periodEnd) {
            throw new InvalidArgumentException('Analytics report period start must not be after its end.');
        }

        if (! is_finite($value)) {
            throw new InvalidArgumentException('Analytics report value must be finite.');
        }

        if ($dimension !== null && mb_strlen($dimension) > self::MAX_DIMENSION_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Analytics report dimension must not exceed %d characters.',
                    self::MAX_DIMENSION_LENGTH,
                ),
            );
        }
    }

    /**
     * Materializes a stored `analytics_reports` row (array keys match
     * the column names: id, metric, span, dimension, period_start,
     * period_end, value, created_at).
     *
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            metric: MetricName::from($row['metric']),
            span: MetricSpan::from($row['span']),
            dimension: $row['dimension'],
            periodStart: new DateTimeImmutable($row['period_start']),
            periodEnd: new DateTimeImmutable($row['period_end']),
            value: (float) $row['value'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toMetric(): MetricName
    {
        return $this->metric;
    }

    public function toSpan(): MetricSpan
    {
        return $this->span;
    }

    public function toDimension(): ?string
    {
        return $this->dimension;
    }

    public function toPeriodStart(): DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function toPeriodEnd(): DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function toValue(): float
    {
        return $this->value;
    }

    public function toCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
