<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\CallToActionClickEvent;
use App\Features\Analytics\Domain\Entities\LanguageSwitchEvent;
use App\Features\Analytics\Domain\Entities\OverallReport;
use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Entities\ThemeSwitchEvent;
use App\Features\Analytics\Domain\Entities\TimeOnPageEvent;
use App\Features\Analytics\Domain\Enums\CallToActionTarget;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use DateTimeImmutable;
use Tests\Support\AnalyticsEventFixture;

/**
 * The `overall` aggregation (analytics.md:176, B287/B288/B289):
 * pure-PHP computation from the typed events into typed results —
 * scalar values (with the previous-period value) and the CTA click
 * table. The constructor IS the aggregation (B289).
 */

/**
 * Builds a typed event with the shared storage fields filled in.
 */
function overallEvent(string $class, array $args, ?string $ip = null, array $extra = []): mixed
{
    return new $class(...array_merge(
        [
            'id' => 0,
            'clientIds' => AnalyticsEventFixture::clientIds(ip: $ip),
            'pageDetails' => AnalyticsEventFixture::pageDetails(),
        ],
        $args,
        ['createdAt' => new DateTimeImmutable('2026-07-08 10:00:00')],
    ));
}

describe('OverallReport', function (): void {
    $period = new ReportPeriod(
        new DateTimeImmutable('2026-07-06 00:00:00'),
        new DateTimeImmutable('2026-07-12 23:59:59'),
    );

    it('computes the typed results from the period events', function () use ($period): void {
        $events = [
            overallEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'A'], 'ip-a'),
            overallEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'B'], 'ip-b'),
            overallEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'C'], 'ip-a'),
            overallEvent(TimeOnPageEvent::class, ['seconds' => 10], 'ip-a'),
            overallEvent(TimeOnPageEvent::class, ['seconds' => 30], 'ip-b'),
            overallEvent(CallToActionClickEvent::class, ['target' => CallToActionTarget::Cv], 'ip-a'),
            overallEvent(CallToActionClickEvent::class, ['target' => CallToActionTarget::Github], 'ip-b'),
            overallEvent(LanguageSwitchEvent::class, ['locale' => 'ru'], 'ip-a'),
            overallEvent(ThemeSwitchEvent::class, ['mode' => 'dark'], 'ip-b'),
        ];

        $report = new OverallReport($period, new DateTimeImmutable('2026-07-13 00:30:00'), events: $events);

        expect($report->reportType())->toBe('overall')
            ->and($report->toScalar('total_page_views')->toValue())->toBe(3)
            ->and($report->toScalar('unique_visitors')->toValue())->toBe(2)
            ->and($report->toScalar('avg_time_on_page')->toValue())->toBe(20.0)
            ->and($report->toResults()['cta_clicks'])->toBeInstanceOf(ReportTableValue::class)
            ->and($report->toResults()['cta_clicks']->toRows())->toBe([
                ['target' => 'github', 'count' => 1],
                ['target' => 'demo', 'count' => 0],
                ['target' => 'cv', 'count' => 1],
                ['target' => 'feedback', 'count' => 0],
                ['target' => 'language', 'count' => 1],
                ['target' => 'theme', 'count' => 1],
            ])
            ->and($report->toPeriodStart())->toEqual($period->toPeriodStart())
            ->and($report->toPeriodEnd())->toEqual($period->toPeriodEnd());
    });

    it('counts events without an ip in the totals but not among visitors', function () use ($period): void {
        $events = [
            overallEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'A'], null),
            overallEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'B'], 'ip-a'),
        ];

        $report = new OverallReport($period, new DateTimeImmutable(), events: $events);

        expect($report->toScalar('total_page_views')->toValue())->toBe(2)
            ->and($report->toScalar('unique_visitors')->toValue())->toBe(1);
    });

    it('reports a null average time without time_on_page events', function () use ($period): void {
        $report = new OverallReport($period, new DateTimeImmutable(), events: []);

        expect($report->toScalar('avg_time_on_page')->toValue())->toBeNull();
    });

    it('carries the previous-period values into the scalars', function () use ($period): void {
        $previous = new OverallReport(
            $period,
            new DateTimeImmutable(),
            events: [overallEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'A'], 'ip-a')],
        );

        $events = array_fill(0, 3, overallEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'B'], 'ip-a'));

        $report = new OverallReport($period, new DateTimeImmutable(), events: $events, previous: $previous);

        $totalPageViews = $report->toScalar('total_page_views');

        expect($totalPageViews)->toBeInstanceOf(ReportScalarValue::class)
            ->and($totalPageViews->toValue())->toBe(3)
            ->and($totalPageViews->toPrevious())->toBe(1)
            ->and($totalPageViews->toChangePercent())->toBe(200.0)
            ->and($report->toScalar('avg_time_on_page')->toPrevious())->toBeNull();
    });

    it('serializes the results and restores them from the payload', function () use ($period): void {
        $report = new OverallReport(
            $period,
            new DateTimeImmutable(),
            events: [overallEvent(PageViewEvent::class, ['referrer' => null, 'title' => 'A'], 'ip-a')],
        );

        $restored = OverallReport::fromPayload(
            7,
            $period,
            array_map(static fn ($result): array => $result->toArray(), $report->toResults()),
            $report->toGeneratedAt(),
        );

        expect($restored->toId())->toBe(7)
            ->and($restored->toScalar('total_page_views')->toValue())->toBe(1);
    });
});
