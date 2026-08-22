<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\App\UseCases\ResetAnalyticsUseCase;
use Tests\Support\Fakes\FakeAnalyticsDailyMetricRepository;
use Tests\Support\Fakes\FakeAnalyticsEventRepository;
use Tests\Support\Fakes\FakeAnalyticsReportRepository;

/**
 * `ResetAnalyticsUseCase` — the `analytics:wipe` backing (analytics.md
 * "Data deletion"): clears all three analytics stores (raw events,
 * daily metrics, reports) so manual testing can start from a fresh
 * state. The per-table deleted counts come back as the wipe result
 * DTO; the actual deletion is the repositories' job (integration-
 * covered).
 */
describe('ResetAnalyticsUseCase', function (): void {
    it('clears all three analytics stores and returns the deleted counts', function (): void {
        $events = new FakeAnalyticsEventRepository(deletedAllCount: 12);
        $metrics = new FakeAnalyticsDailyMetricRepository(deletedAllCount: 34);
        $reports = new FakeAnalyticsReportRepository(deletedAllCount: 56);

        $result = (new ResetAnalyticsUseCase($events, $metrics, $reports))->wipe();

        expect($events->deletedAllCalls)->toBe(1)
            ->and($metrics->deletedAllCalls)->toBe(1)
            ->and($reports->deletedAllCalls)->toBe(1)
            ->and($result->events)->toBe(12)
            ->and($result->metrics)->toBe(34)
            ->and($result->reports)->toBe(56);
    });
});
