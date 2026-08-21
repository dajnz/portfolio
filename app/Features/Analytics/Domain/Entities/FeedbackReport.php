<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use DateTimeImmutable;

/**
 * The `feedback` report (analytics.md:179, B287/B288): the submission
 * scalar (with the previous-period value) and the per-day submissions
 * series. Its input is not analytics events but the `feedback`
 * submissions (the cross-feature reader provides the `created_at`
 * timestamps, B11) — the daily grouping is the report-specific
 * computation encapsulated here.
 */
final class FeedbackReport extends AnalyticsReport
{
    public const REPORT_TYPE = 'feedback';

    /**
     * The constructor IS the aggregation (B289): with `$submissions`
     * given, the results are computed from them (fresh generation);
     * without them, `$results` must hold the stored results (the
     * persisted-form path used by the factory).
     *
     * @param list<\DateTimeImmutable>|null $submissions
     */
    public function __construct(
        ReportPeriod $period,
        DateTimeImmutable $generatedAt,
        array $results = [],
        ?array $submissions = null,
        ?AnalyticsReport $previous = null,
        int $id = 0,
    ) {
        if ($submissions !== null) {
            $results = $this->aggregateFrom($submissions, $previous);
        }

        parent::__construct($id, $period, $results, $generatedAt);
    }

    /**
     * The persisted-form constructor — the one needed static (B289).
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(
        int $id,
        ReportPeriod $period,
        array $payload,
        DateTimeImmutable $generatedAt,
    ): self {
        return new self(
            $period,
            $generatedAt,
            [
                'total_submissions' => ReportScalarValue::fromArray($payload['total_submissions']),
                'submissions_per_day' => ReportSeriesValue::fromArray($payload['submissions_per_day']),
            ],
            id: $id,
        );
    }

    /**
     * @param list<\DateTimeImmutable> $submissions
     *
     * @return array<string, ReportScalarValue|ReportSeriesValue>
     */
    private function aggregateFrom(array $submissions, ?AnalyticsReport $previous): array
    {
        return [
            'total_submissions' => new ReportScalarValue(
                count($submissions),
                $this->previousScalarValue($previous, 'total_submissions'),
            ),
            'submissions_per_day' => $this->submissionsSeries($this->groupPerDay($submissions)),
        ];
    }

    /**
     * @param list<\DateTimeImmutable> $submissions
     *
     * @return array<string, int> date → count
     */
    private function groupPerDay(array $submissions): array
    {
        $perDay = [];

        foreach ($submissions as $createdAt) {
            $day = $createdAt->format('Y-m-d');
            $perDay[$day] = ($perDay[$day] ?? 0) + 1;
        }

        ksort($perDay);

        return $perDay;
    }

    /**
     * @param array<string, int> $perDay
     */
    private function submissionsSeries(array $perDay): ReportSeriesValue
    {
        $points = [];

        foreach ($perDay as $day => $count) {
            $points[] = [
                'label' => $day,
                'value' => $count,
            ];
        }

        return new ReportSeriesValue($points);
    }
}
