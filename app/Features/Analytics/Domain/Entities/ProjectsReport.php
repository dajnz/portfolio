<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use DateTimeImmutable;

/**
 * The `projects` report (analytics.md:178, B286/B288/B289): period
 * views per project as a table, counted from `page_view` events whose
 * page context points at a project detail page
 * (`toProjectId()` — B289: the frontend sends the page type + entity
 * id, no URL lookup). The EN titles come from the cross-feature
 * reader.
 */
final class ProjectsReport extends AnalyticsReport
{
    public const REPORT_TYPE = 'projects';

    /**
     * The constructor IS the aggregation (B289): with `$events` given,
     * the results are computed from the period's events; without them,
     * `$results` must hold the stored results (the persisted-form path
     * used by the factory).
     *
     * @param list<AnalyticsEvent>|null $events
     * @param array<int, string> $projectTitles
     */
    public function __construct(
        ReportPeriod $period,
        DateTimeImmutable $generatedAt,
        array $results = [],
        ?array $events = null,
        array $projectTitles = [],
        int $id = 0,
    ) {
        if ($events !== null) {
            $results = $this->aggregateFrom($events, $projectTitles);
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
                'project_views' => ReportTableValue::fromArray($payload['project_views']),
            ],
            id: $id,
        );
    }

    /**
     * @param list<AnalyticsEvent> $events
     * @param array<int, string> $projectTitles
     *
     * @return array<string, ReportTableValue>
     */
    private function aggregateFrom(array $events, array $projectTitles): array
    {
        return [
            'project_views' => $this->projectViewsTable($this->accumulate($events), $projectTitles),
        ];
    }

    /**
     * @param list<AnalyticsEvent> $events
     *
     * @return array<int, int> project id → view count
     */
    private function accumulate(array $events): array
    {
        $viewsByProject = [];

        foreach ($events as $event) {
            if (! $event instanceof PageViewEvent) {
                continue;
            }

            $projectId = $event->toPageDetails()->toProjectId();

            if ($projectId !== null) {
                $viewsByProject[$projectId] = ($viewsByProject[$projectId] ?? 0) + 1;
            }
        }

        return $viewsByProject;
    }

    /**
     * @param array<int, int> $viewsByProject
     * @param array<int, string> $titles
     */
    private function projectViewsTable(array $viewsByProject, array $titles): ReportTableValue
    {
        $rows = [];

        foreach ($viewsByProject as $projectId => $views) {
            $rows[] = [
                'id' => $projectId,
                'title' => $titles[$projectId] ?? '',
                'views' => $views,
            ];
        }

        usort(
            $rows,
            static fn (array $a, array $b): int => $b['views'] <=> $a['views'] ?: $a['id'] <=> $b['id'],
        );

        return new ReportTableValue(['id', 'title', 'views'], $rows);
    }
}
