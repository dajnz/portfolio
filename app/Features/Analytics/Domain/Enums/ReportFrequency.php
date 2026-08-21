<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Enums;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The `analytics_report_frequency` setting values (database.md:49) —
 * drives the period length of every report type on the same cadence
 * (analytics.md:162). Periods are calendar-aligned in the server's
 * local timezone (analytics.md:164-172).
 */
enum ReportFrequency: string
{
    case Daily = 'daily';

    case Weekly = 'weekly';

    case Monthly = 'monthly';

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidArgumentException(
            sprintf(
                "Report frequency must be one of: %s.",
                implode(', ', array_column(self::cases(), 'value')),
            ),
        );
    }

    /** The in-progress period containing `$now`. */
    public function currentPeriod(DateTimeImmutable $now): ReportPeriod
    {
        return new ReportPeriod(
            $this->periodStart($now),
            $this->periodEnd($now),
        );
    }

    /** The just-completed period preceding `$now` (B219). */
    public function previousPeriod(DateTimeImmutable $now): ReportPeriod
    {
        return $this->currentPeriod($this->shiftOnePeriodBack($now));
    }

    private function shiftOnePeriodBack(DateTimeImmutable $now): DateTimeImmutable
    {
        return match ($this) {
            self::Daily => $now->modify('-1 day'),
            self::Weekly => $now->modify('-1 week'),
            self::Monthly => $now->modify('first day of previous month'),
        };
    }

    private function periodStart(DateTimeImmutable $now): DateTimeImmutable
    {
        return match ($this) {
            self::Daily => $now->setTime(0, 0, 0),
            self::Weekly => $now->modify('monday this week')->setTime(0, 0, 0),
            self::Monthly => $now->modify('first day of this month')->setTime(0, 0, 0),
        };
    }

    private function periodEnd(DateTimeImmutable $now): DateTimeImmutable
    {
        return match ($this) {
            self::Daily => $now->setTime(23, 59, 59),
            self::Weekly => $now->modify('sunday this week')->setTime(23, 59, 59),
            self::Monthly => $now->modify('last day of this month')->setTime(23, 59, 59),
        };
    }
}
