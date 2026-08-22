<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\UseCases\AggregateDailyMetricsUseCase;
use App\Features\Analytics\App\UseCases\RebuildReportsUseCase;
use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Infra\Jobs\RebuildReportsJob;
use DateTimeImmutable;
use Tests\Support\AnalyticsEventFixture;
use Tests\Support\AnalyticsMetricFixture;
use Tests\Support\Fakes\FakeAnalyticsDailyMetricRepository;
use Tests\Support\Fakes\FakeAnalyticsEventRepository;
use Tests\Support\Fakes\FakeAnalyticsReportDataReader;
use Tests\Support\Fakes\FakeAnalyticsReportRepository;

/**
 * The report rebuild job (analytics.md:186-212): the daily path
 * rebuilds the reports only (the metrics were just aggregated); the
 * full-rebuild path (`recomputeMetrics` — the manual command and the
 * dashboard "Generate Now") deletes the metric rows for the dates
 * covered by the still-present events, re-aggregates them from the
 * oldest existing event → today (plus the FULL feedback history —
 * feedback rows are never pruned, so `feedback_submissions` is
 * re-derived even for days before the event window), and keeps the
 * metrics older than the event window.
 */

/**
 * Runs the job against the fakes and returns the report-repository
 * double for the `replaceAll` assertions.
 */
function runRebuildJob(
    bool $recomputeMetrics,
    FakeAnalyticsEventRepository $events,
    FakeAnalyticsReportDataReader $dataReader,
    FakeAnalyticsDailyMetricRepository $metrics,
): FakeAnalyticsReportRepository {
    $reports = new FakeAnalyticsReportRepository();

    $job = new RebuildReportsJob($recomputeMetrics);
    $job->handle(new RebuildReportsUseCase(
        metrics: $metrics,
        reports: $reports,
        events: $events,
        dataReader: $dataReader,
        aggregate: new AggregateDailyMetricsUseCase($events, $dataReader, $metrics),
    ));

    return $reports;
}

/**
 * The end-of-today bound the recompute window extends to.
 */
function rebuildWindowEnd(): DateTimeImmutable
{
    return (new DateTimeImmutable('today'))->modify('+1 day')->modify('-1 second');
}

/**
 * @param list<AnalyticsMetric> $rows
 *
 * @return list<AnalyticsMetric>
 */
function metricRowsByName(array $rows, MetricName $metric): array
{
    return array_values(array_filter(
        $rows,
        static fn (AnalyticsMetric $row): bool => $row->toMetric() === $metric,
    ));
}

describe('RebuildReportsJob', function (): void {
    it('rebuilds the reports without touching the metrics on the daily path', function (): void {
        $metrics = new FakeAnalyticsDailyMetricRepository();
        $metrics->groups[MetricSpan::All->value] = [AnalyticsMetricFixture::metricGroup()];

        $reports = runRebuildJob(
            recomputeMetrics: false,
            events: new FakeAnalyticsEventRepository(oldestEventDate: new DateTimeImmutable('2026-07-09')),
            dataReader: new FakeAnalyticsReportDataReader(oldestFeedbackDate: new DateTimeImmutable('2026-07-01')),
            metrics: $metrics,
        );

        expect($metrics->deletedRanges)->toBe([])
            ->and($metrics->stored)->toBe([])
            ->and($reports->replaced)->toHaveCount(1);
    });

    it('deletes the event-window metrics and re-aggregates them from the oldest event', function (): void {
        $events = new FakeAnalyticsEventRepository(
            periodEvents: [rebuildPageViewEvent(new DateTimeImmutable('2026-07-09 10:00:00'))],
            oldestEventDate: new DateTimeImmutable('2026-07-09'),
        );
        $metrics = new FakeAnalyticsDailyMetricRepository();

        runRebuildJob(
            recomputeMetrics: true,
            events: $events,
            dataReader: new FakeAnalyticsReportDataReader(),
            metrics: $metrics,
        );

        expect($metrics->deletedRanges)->toHaveCount(1);

        [$dateFrom, $dateTo] = $metrics->deletedRanges[0];

        expect($dateFrom->format('Y-m-d'))->toBe('2026-07-09')
            ->and($dateTo->format('Y-m-d H:i:s'))->toBe(rebuildWindowEnd()->format('Y-m-d H:i:s'));

        $pageViews = metricRowsByName($metrics->stored, MetricName::PageViews);

        expect($pageViews)->toHaveCount(1)
            ->and($pageViews[0]->toDate()->format('Y-m-d'))->toBe('2026-07-09')
            ->and($pageViews[0]->toValue())->toBe(1.0);
    });

    it('re-aggregates from the oldest feedback when it precedes the event window', function (): void {
        $events = new FakeAnalyticsEventRepository(
            periodEvents: [rebuildPageViewEvent(new DateTimeImmutable('2026-07-09 10:00:00'))],
            oldestEventDate: new DateTimeImmutable('2026-07-09'),
        );
        $dataReader = new FakeAnalyticsReportDataReader(
            submissions: [
                new DateTimeImmutable('2026-06-01 11:00:00'),
                new DateTimeImmutable('2026-07-09 11:00:00'),
            ],
            oldestFeedbackDate: new DateTimeImmutable('2026-06-01'),
        );
        $metrics = new FakeAnalyticsDailyMetricRepository();

        runRebuildJob(recomputeMetrics: true, events: $events, dataReader: $dataReader, metrics: $metrics);

        // The deletion still covers only the event window — metrics
        // older than the window are kept.
        expect($metrics->deletedRanges)->toHaveCount(1);

        [$dateFrom] = $metrics->deletedRanges[0];

        expect($dateFrom->format('Y-m-d'))->toBe('2026-07-09');

        // ...but the aggregation starts at the oldest feedback row.
        $feedback = metricRowsByName($metrics->stored, MetricName::FeedbackSubmissions);

        expect(array_map(
            static fn (AnalyticsMetric $row): string => $row->toDate()->format('Y-m-d'),
            $feedback,
        ))->toBe(['2026-06-01', '2026-07-09']);
    });

    it('skips the metric recompute when no events and no feedback exist', function (): void {
        $metrics = new FakeAnalyticsDailyMetricRepository();

        $reports = runRebuildJob(
            recomputeMetrics: true,
            events: new FakeAnalyticsEventRepository(),
            dataReader: new FakeAnalyticsReportDataReader(),
            metrics: $metrics,
        );

        expect($metrics->deletedRanges)->toBe([])
            ->and($metrics->stored)->toBe([])
            ->and($reports->replaceAllCalls)->toBe(1);
    });

    it('ignores events dated in the future', function (): void {
        $events = new FakeAnalyticsEventRepository(
            periodEvents: [rebuildPageViewEvent(new DateTimeImmutable('2099-01-01 10:00:00'))],
            oldestEventDate: new DateTimeImmutable('2099-01-01'),
        );
        $metrics = new FakeAnalyticsDailyMetricRepository();

        runRebuildJob(
            recomputeMetrics: true,
            events: $events,
            dataReader: new FakeAnalyticsReportDataReader(),
            metrics: $metrics,
        );

        expect($metrics->deletedRanges)->toBe([])
            ->and($metrics->stored)->toBe([]);
    });
});

/**
 * A single landing page-view event for the aggregation harness.
 */
function rebuildPageViewEvent(DateTimeImmutable $createdAt): PageViewEvent
{
    return new PageViewEvent(
        id: 1,
        clientIds: AnalyticsEventFixture::clientIds('s-1', 'ip-a'),
        pageDetails: AnalyticsEventFixture::pageDetails(PageType::Landing),
        referrer: null,
        title: 'Landing',
        createdAt: $createdAt,
    );
}
