<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Shared\Domain\BaseEntity;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * One row of `analytics_daily_metrics` (database.md:513-529, B290):
 * the per-day aggregate of a metric for one dimension — NULL dimension
 * = global, otherwise the entity key (page URL, `article:{id}`,
 * `project:{id}`, CTA target). `value` + `samples` store the weighted
 * sum/samples pair (never averages — every report span's average is
 * computed as `SUM(value)/SUM(samples)`), or the plain min/max value
 * with `samples = 1`.
 *
 * The constructor protects the invariants: samples are always at
 * least 1, the value must be finite, and the dimension must fit the
 * `varchar(500)` column.
 */
final class AnalyticsMetric extends BaseEntity
{
    public const MAX_DIMENSION_LENGTH = 500;

    public function __construct(
        private readonly int $id,
        private readonly MetricName $metric,
        private readonly DateTimeImmutable $date,
        private readonly ?string $dimension,
        private readonly float $value,
        private readonly int $samples,
        private readonly DateTimeImmutable $createdAt,
    ) {
        if ($samples < 1) {
            throw new InvalidArgumentException('Analytics metric samples must be at least 1.');
        }

        if (! is_finite($value)) {
            throw new InvalidArgumentException('Analytics metric value must be finite.');
        }

        if ($dimension !== null && mb_strlen($dimension) > self::MAX_DIMENSION_LENGTH) {
            throw new InvalidArgumentException(
                sprintf(
                    'Analytics metric dimension must not exceed %d characters.',
                    self::MAX_DIMENSION_LENGTH,
                ),
            );
        }
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toMetric(): MetricName
    {
        return $this->metric;
    }

    public function toDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function toDimension(): ?string
    {
        return $this->dimension;
    }

    public function toValue(): float
    {
        return $this->value;
    }

    public function toSamples(): int
    {
        return $this->samples;
    }

    public function toCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
