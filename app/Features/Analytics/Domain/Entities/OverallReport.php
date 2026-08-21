<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use DateTimeImmutable;

/**
 * The `overall` report (analytics.md:176, B287/B288/B289): the scalar
 * results (total page views, unique visitors via `COUNT(DISTINCT ip)`
 * across ALL events in the period, average time on page — each with
 * the previous-period value) and the CTA click breakdown as a table.
 * The CTA clicks are counted from the event types, not from payload
 * metadata (B287 — the frontend sends none; the type suffix IS the
 * target).
 */
final class OverallReport extends AnalyticsReport
{
    public const REPORT_TYPE = 'overall';

    public const CTA_TARGETS = ['github', 'demo', 'cv', 'feedback', 'language', 'theme'];

    /**
     * The constructor IS the aggregation (B289): with `$events` given,
     * the results are computed from the period's events (fresh
     * generation); without them, `$results` must hold the stored
     * results (the persisted-form path used by the factory).
     *
     * @param list<AnalyticsEvent>|null $events
     */
    public function __construct(
        ReportPeriod $period,
        DateTimeImmutable $generatedAt,
        array $results = [],
        ?array $events = null,
        ?AnalyticsReport $previous = null,
        int $id = 0,
    ) {
        if ($events !== null) {
            $results = $this->aggregateFrom($events, $previous);
        }

        parent::__construct($id, $period, $results, $generatedAt);
    }

    /**
     * The persisted-form constructor — the one needed static (B289):
     * the aggregation constructor cannot rebuild stored results (PHP
     * has a single constructor), so the row-to-entity parsing lives
     * here, keeping the per-type result structure in the entity.
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
                'total_page_views' => ReportScalarValue::fromArray($payload['total_page_views']),
                'unique_visitors' => ReportScalarValue::fromArray($payload['unique_visitors']),
                'avg_time_on_page' => ReportScalarValue::fromArray($payload['avg_time_on_page']),
                'cta_clicks' => ReportTableValue::fromArray($payload['cta_clicks']),
            ],
            id: $id,
        );
    }

    /**
     * @param list<AnalyticsEvent> $events
     *
     * @return array<string, ReportScalarValue|ReportTableValue>
     */
    private function aggregateFrom(array $events, ?AnalyticsReport $previous): array
    {
        $totals = $this->accumulate($events);

        return [
            'total_page_views' => new ReportScalarValue(
                $totals['page_views'],
                $this->previousScalarValue($previous, 'total_page_views'),
            ),
            'unique_visitors' => new ReportScalarValue(
                count($totals['visitors']),
                $this->previousScalarValue($previous, 'unique_visitors'),
            ),
            'avg_time_on_page' => new ReportScalarValue(
                $this->average($totals['seconds_total'], $totals['seconds_samples']),
                $this->previousScalarValue($previous, 'avg_time_on_page'),
            ),
            'cta_clicks' => $this->ctaTable($totals['cta_clicks']),
        ];
    }

    /**
     * @param list<AnalyticsEvent> $events
     *
     * @return array{
     *     page_views: int,
     *     visitors: array<string, true>,
     *     seconds_total: int,
     *     seconds_samples: int,
     *     cta_clicks: array<string, int>,
     * }
     */
    private function accumulate(array $events): array
    {
        $pageViews = 0;
        $visitors = [];
        $secondsTotal = 0;
        $secondsSamples = 0;
        $ctaClicks = array_fill_keys(self::CTA_TARGETS, 0);

        foreach ($events as $event) {
            $this->accumulateVisitor($event, $visitors);

            if ($event instanceof PageViewEvent) {
                $pageViews++;
            } elseif ($event instanceof TimeOnPageEvent) {
                $secondsTotal += $event->toSeconds();
                $secondsSamples++;
            } elseif ($event instanceof CallToActionClickEvent) {
                $ctaClicks[$event->toTarget()->value]++;
            } elseif ($event instanceof LanguageSwitchEvent) {
                $ctaClicks['language']++;
            } elseif ($event instanceof ThemeSwitchEvent) {
                $ctaClicks['theme']++;
            }
        }

        return [
            'page_views' => $pageViews,
            'visitors' => $visitors,
            'seconds_total' => $secondsTotal,
            'seconds_samples' => $secondsSamples,
            'cta_clicks' => $ctaClicks,
        ];
    }

    /**
     * @param array<string, true> $visitors
     */
    private function accumulateVisitor(AnalyticsEvent $event, array &$visitors): void
    {
        if ($event->toIpAddress() !== null) {
            $visitors[$event->toIpAddress()] = true;
        }
    }

    /**
     * @param array<string, int> $clicks
     */
    private function ctaTable(array $clicks): ReportTableValue
    {
        $rows = [];

        foreach (self::CTA_TARGETS as $target) {
            $rows[] = [
                'target' => $target,
                'count' => $clicks[$target],
            ];
        }

        return new ReportTableValue(['target', 'count'], $rows);
    }
}
