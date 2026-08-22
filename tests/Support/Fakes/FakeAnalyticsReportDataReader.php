<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * In-memory `AnalyticsReportDataReaderInterface` double: returns the
 * configured submission timestamps for any period, the configured
 * oldest feedback date and the configured id → EN title maps for the
 * ranking dimension lookups.
 */
final class FakeAnalyticsReportDataReader implements AnalyticsReportDataReaderInterface
{
    /** @param list<DateTimeImmutable> $submissions
     * @param array<int, string> $articleTitles
     * @param array<int, string> $projectTitles
     */
    public function __construct(
        public readonly array $submissions = [],
        public readonly ?DateTimeImmutable $oldestFeedbackDate = null,
        public readonly array $articleTitles = [],
        public readonly array $projectTitles = [],
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

    public function articleTitles(array $articleIds): array
    {
        return array_intersect_key($this->articleTitles, array_flip($articleIds));
    }

    public function projectTitles(array $projectIds): array
    {
        return array_intersect_key($this->projectTitles, array_flip($projectIds));
    }
}
