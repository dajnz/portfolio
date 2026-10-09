<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calendar-aligned report period: a day runs 00:00→23:59:59, a week
 * Monday→Sunday, a month the 1st→last day, in the server's local
 * timezone. Immutable — the bounds are fixed at construction.
 */
final class ReportPeriod
{
    public function __construct(
        private readonly DateTimeImmutable $periodStart,
        private readonly DateTimeImmutable $periodEnd,
    ) {
        if ($periodStart > $periodEnd) {
            throw new InvalidArgumentException('Report period start must not be after its end.');
        }
    }

    public function toPeriodStart(): DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function toPeriodEnd(): DateTimeImmutable
    {
        return $this->periodEnd;
    }

    /**
     * Exclusive upper bound for `created_at` range filters. An inclusive
     * `<= periodEnd` would drop events timestamped after 23:59:59.000
     * (PostgreSQL timestamps carry microseconds).
     */
    public function toPeriodEndExclusive(): DateTimeImmutable
    {
        return $this->periodEnd->modify('+1 second');
    }
}
