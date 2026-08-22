<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Enums;

/**
 * The metric catalog (analytics.md:172-178, B290) — the full set of
 * names stored in `analytics_daily_metrics.metric` and
 * `analytics_reports.metric`. Aggregation rules per metric kind:
 * count/sum → `SUM`, min → `MIN`, max → `MAX`, avg-able
 * (`*_seconds`, `completion_rate_sum`) → `SUM(value)/SUM(samples)`.
 * The same name may carry several dimension scopes (e.g. global +
 * per-article `views`) — the scope lives in the `dimension` column,
 * not in the name.
 */
enum MetricName: string
{
    // Global (dimension NULL).
    case PageViews = 'page_views';
    case UniqueVisitors = 'unique_visitors';
    case FeedbackSubmissions = 'feedback_submissions';
    case TimeOnPageSeconds = 'time_on_page_seconds';
    case TimeOnPageSamples = 'time_on_page_samples';
    case TimeOnPageMin = 'time_on_page_min';
    case TimeOnPageMax = 'time_on_page_max';
    case BlogViews = 'blog_views';
    case BlogUniqueVisitors = 'blog_unique_visitors';
    case ProjectUniqueVisitors = 'project_unique_visitors';
    case ReadTimeSeconds = 'read_time_seconds';
    case ReadTimeSamples = 'read_time_samples';
    case ReadTimeMin = 'read_time_min';
    case ReadTimeMax = 'read_time_max';
    case CompletionRateSum = 'completion_rate_sum';
    case CompletionRateSamples = 'completion_rate_samples';
    case CompletionRateMin = 'completion_rate_min';
    case CompletionRateMax = 'completion_rate_max';
    case ReactionsLikes = 'reactions_likes';
    case ReactionsDislikes = 'reactions_dislikes';

    // CTA (dimension = target: github|demo|cv|feedback|language|theme).
    case CtaClicks = 'cta_clicks';

    // Per article (`article:{id}`) / per project (`project:{id}`).
    case Views = 'views';

    /**
     * Whether the metric aggregates as a weighted average across spans
     * (`SUM(value)/SUM(samples)` — the `*_seconds` and
     * `completion_rate_sum` metrics, analytics.md:172).
     */
    public function isWeightedAverage(): bool
    {
        return str_ends_with($this->value, '_seconds')
            || $this === self::CompletionRateSum;
    }

    /**
     * Whether the metric aggregates as the plain MIN across spans
     * (the `*_min` metrics).
     */
    public function isMinimum(): bool
    {
        return str_ends_with($this->value, '_min');
    }

    /**
     * Whether the metric aggregates as the plain MAX across spans
     * (the `*_max` metrics).
     */
    public function isMaximum(): bool
    {
        return str_ends_with($this->value, '_max');
    }
}
