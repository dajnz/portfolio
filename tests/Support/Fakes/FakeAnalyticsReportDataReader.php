<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * In-memory `AnalyticsReportDataReaderInterface` double: returns the
 * configured submission timestamps for any period and the configured
 * oldest feedback date.
 */
final class FakeAnalyticsReportDataReader implements AnalyticsReportDataReaderInterface
{
    /** @param list<DateTimeImmutable> $submissions */
    public function __construct(
        public readonly array $submissions = [],
        public readonly ?DateTimeImmutable $oldestFeedbackDate = null,
    ) {
    }

    public function feedbackSubmissions(ReportPeriod $period): array
    {
        return $this->submissions;
    }

    public function oldestFeedbackDate(): ?DateTimeImmutable
    {
        return $this->oldestFeedbackDate;
    }
}
