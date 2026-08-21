<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Entities\ProjectsReport;
use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use DateTimeImmutable;
use Tests\Support\AnalyticsEventFixture;

/**
 * The `projects` aggregation (analytics.md:178, B286/B288/B289):
 * period views per project as a table, from `page_view` events whose
 * page context points at a project detail page. The constructor IS the
 * aggregation (B289).
 */
describe('ProjectsReport', function (): void {
    $period = new ReportPeriod(
        new DateTimeImmutable('2026-07-06 00:00:00'),
        new DateTimeImmutable('2026-07-12 23:59:59'),
    );
    $titles = [1 => 'Ledger App', 2 => 'Payments Gateway'];

    function projectView(?PageType $type, ?int $entityId, string $ip): PageViewEvent
    {
        return new PageViewEvent(
            0,
            AnalyticsEventFixture::clientIds(ip: $ip),
            AnalyticsEventFixture::pageDetails($type, $entityId),
            null,
            null,
            new DateTimeImmutable(),
        );
    }

    it('counts page views per project detail page', function () use ($period, $titles): void {
        $events = [
            projectView(PageType::ProjectDetail, 1, 'ip-a'),
            projectView(PageType::ProjectDetail, 1, 'ip-b'),
            projectView(PageType::ProjectDetail, 2, 'ip-c'),
            projectView(PageType::BlogArticle, 1, 'ip-d'),
            projectView(PageType::Landing, null, 'ip-e'),
        ];

        $report = new ProjectsReport($period, new DateTimeImmutable(), events: $events, projectTitles: $titles);

        expect($report->toResults()['project_views'])->toBeInstanceOf(ReportTableValue::class)
            ->and($report->toResults()['project_views']->toRows())->toBe([
                ['id' => 1, 'title' => 'Ledger App', 'views' => 2],
                ['id' => 2, 'title' => 'Payments Gateway', 'views' => 1],
            ]);
    });

    it('renders an empty table without project views', function () use ($period, $titles): void {
        $report = new ProjectsReport($period, new DateTimeImmutable(), events: [], projectTitles: $titles);

        expect($report->toResults()['project_views']->toRows())->toBe([]);
    });

    it('serializes the results and restores them from the payload', function () use ($period, $titles): void {
        $report = new ProjectsReport(
            $period,
            new DateTimeImmutable(),
            events: [projectView(PageType::ProjectDetail, 1, 'ip-a')],
            projectTitles: $titles,
        );

        $restored = ProjectsReport::fromPayload(
            7,
            $period,
            array_map(static fn ($result): array => $result->toArray(), $report->toResults()),
            $report->toGeneratedAt(),
        );

        expect($restored->toId())->toBe(7)
            ->and($restored->toResults()['project_views']->toRows())->toBe([
                ['id' => 1, 'title' => 'Ledger App', 'views' => 1],
            ]);
    });
});
