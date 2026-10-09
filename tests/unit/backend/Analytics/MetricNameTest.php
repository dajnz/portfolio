<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Enums\MetricName;

describe('MetricName', function (): void {
    it('covers the full metric catalog from the spec', function (): void {
        expect(array_column(MetricName::cases(), 'value'))->toBe([
            'page_views',
            'unique_visitors',
            'feedback_submissions',
            'time_on_page_seconds',
            'time_on_page_samples',
            'time_on_page_min',
            'time_on_page_max',
            'blog_views',
            'blog_unique_visitors',
            'project_unique_visitors',
            'read_time_seconds',
            'read_time_samples',
            'read_time_min',
            'read_time_max',
            'completion_rate_sum',
            'completion_rate_samples',
            'completion_rate_min',
            'completion_rate_max',
            'reactions_likes',
            'reactions_dislikes',
            'cta_clicks',
            'views',
        ]);
    });

    it('keeps the CTA target and the entity-key scopes as dimensions, not names', function (): void {
        expect(MetricName::CtaClicks->value)->toBe('cta_clicks')
            ->and(MetricName::Views->value)->toBe('views')
            ->and(MetricName::TimeOnPageSeconds->value)->toBe('time_on_page_seconds');
    });

    it('classifies the span aggregation rule per metric kind', function (): void {
        // avg-able (`*_seconds`, `completion_rate_sum`) → SUM(value)/SUM(samples).
        expect(MetricName::TimeOnPageSeconds->isWeightedAverage())->toBeTrue()
            ->and(MetricName::ReadTimeSeconds->isWeightedAverage())->toBeTrue()
            ->and(MetricName::CompletionRateSum->isWeightedAverage())->toBeTrue()
            ->and(MetricName::PageViews->isWeightedAverage())->toBeFalse()
            ->and(MetricName::TimeOnPageSamples->isWeightedAverage())->toBeFalse()
            ->and(MetricName::TimeOnPageMin->isWeightedAverage())->toBeFalse();

        // `*_min` → MIN(value).
        expect(MetricName::TimeOnPageMin->isMinimum())->toBeTrue()
            ->and(MetricName::ReadTimeMin->isMinimum())->toBeTrue()
            ->and(MetricName::CompletionRateMin->isMinimum())->toBeTrue()
            ->and(MetricName::TimeOnPageSeconds->isMinimum())->toBeFalse()
            ->and(MetricName::PageViews->isMinimum())->toBeFalse();

        // `*_max` → MAX(value).
        expect(MetricName::TimeOnPageMax->isMaximum())->toBeTrue()
            ->and(MetricName::ReadTimeMax->isMaximum())->toBeTrue()
            ->and(MetricName::CompletionRateMax->isMaximum())->toBeTrue()
            ->and(MetricName::CtaClicks->isMaximum())->toBeFalse()
            ->and(MetricName::TimeOnPageSeconds->isMaximum())->toBeFalse();
    });
});
