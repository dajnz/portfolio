<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;

/**
 * Shared factory helpers for the analytics metric-group rows: the canned
 * `aggregateForSpan` group shape the report-rebuild tests feed into the
 * fake daily-metric repository.
 */
final class AnalyticsMetricFixture
{
    /**
     * A canned `aggregateForSpan` group row (the repository contract
     * shape: metric, dimension, period bounds + the raw group stats).
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public static function metricGroup(array $attributes = []): array
    {
        return [
            'metric' => 'page_views',
            'dimension' => null,
            'period_start' => new DateTimeImmutable('2026-06-01'),
            'period_end' => new DateTimeImmutable('2026-08-21'),
            'value_sum' => 100.0,
            'samples_sum' => 100,
            'value_min' => 1.0,
            'value_max' => 10.0,
            ...$attributes,
        ];
    }
}
