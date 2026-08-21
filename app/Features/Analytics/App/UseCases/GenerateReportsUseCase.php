<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Entities\BlogReport;
use App\Features\Analytics\Domain\Entities\FeedbackReport;
use App\Features\Analytics\Domain\Entities\OverallReport;
use App\Features\Analytics\Domain\Entities\ProjectsReport;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use DateTimeImmutable;

/**
 * Aggregates one period into the four reports and persists them via
 * the unified repository (analytics.md:174-187, B288/B289). All four
 * report types share the single period length set by
 * `analytics_report_frequency` (analytics.md:162). The computation is
 * encapsulated in the typed report entities' constructors (B289):
 * this use case loads the period's events (as typed entities),
 * supplies the cross-feature titles + submission timestamps through
 * the app-layer reader interface (B11) and the previous-period report
 * (the scalar `previous` values) and persists the results. Returns the
 * serialized results so the job can compose the report mail.
 */
final class GenerateReportsUseCase
{
    public function __construct(
        private readonly AnalyticsEventRepositoryInterface $events,
        private readonly AnalyticsReportDataReaderInterface $dataReader,
        private readonly AnalyticsReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @return array{
     *     overall: array<string, mixed>,
     *     blog: array<string, mixed>,
     *     projects: array<string, mixed>,
     *     feedback: array<string, mixed>,
     * }
     */
    public function generate(ReportPeriod $period): array
    {
        $events = $this->events->eventsBetween($period);
        $generatedAt = new DateTimeImmutable();

        $overall = new OverallReport(
            $period,
            $generatedAt,
            events: $events,
            previous: $this->previous(OverallReport::REPORT_TYPE, $period),
        );
        $blog = new BlogReport(
            $period,
            $generatedAt,
            events: $events,
            articleTitles: $this->dataReader->articleTitles(),
            previous: $this->previous(BlogReport::REPORT_TYPE, $period),
        );
        $projects = new ProjectsReport(
            $period,
            $generatedAt,
            events: $events,
            projectTitles: $this->dataReader->projectTitles(),
        );
        $feedback = new FeedbackReport(
            $period,
            $generatedAt,
            submissions: $this->dataReader->feedbackSubmissions($period),
            previous: $this->previous(FeedbackReport::REPORT_TYPE, $period),
        );

        foreach ([$overall, $blog, $projects, $feedback] as $report) {
            $this->reports->save($report);
        }

        return [
            'overall' => $this->serializeResults($overall),
            'blog' => $this->serializeResults($blog),
            'projects' => $this->serializeResults($projects),
            'feedback' => $this->serializeResults($feedback),
        ];
    }

    private function previous(string $reportType, ReportPeriod $period): ?AnalyticsReport
    {
        return $this->reports->findNewestBefore($reportType, $period);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function serializeResults(AnalyticsReport $report): array
    {
        return array_map(
            static fn (ReportScalarValue|ReportSeriesValue|ReportTableValue $result): array => $result->toArray(),
            $report->toResults(),
        );
    }
}
