<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\DTOs;

use DateTimeImmutable;

/**
 * The daily email digest payload (analytics.md:214-219, ADR-0017, 11.3.5):
 * the previous day's key metrics — page views, unique visitors,
 * feedback submissions — and the top article by views (null when the
 * day has no article rows with an EN translation). Produced by
 * `GetDailyDigestUseCase` and carried by `SendAnalyticsReportMail`.
 */
final readonly class AnalyticsDigestDto
{
    public function __construct(
        public DateTimeImmutable $day,
        public float $pageViews,
        public float $uniqueVisitors,
        public float $feedbackSubmissions,
        public ?AnalyticsTopArticleDto $topArticle,
    ) {
    }
}
