<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\App\DTOs\AnalyticsDigestDto;
use App\Features\Analytics\App\DTOs\AnalyticsTopArticleDto;
use App\Features\Analytics\Domain\Entities\AnalyticsMetric;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use DateTimeImmutable;

/**
 * The email digest payload (analytics.md:214-219, ADR-0017, 11.3.5) for
 * one day: the global `page_views`/`unique_visitors`/
 * `feedback_submissions` rows (zero when the day has none) and the top
 * article by views — the `views` row with an `article:{id}` dimension
 * carrying the most views. The article is shown only when it has an EN
 * translation (the rankings' display-name rule, ADR-0017 — untranslated
 * rows are dropped, the next best article wins); without any titled
 * article the digest carries no top article. Used by
 * `AggregateDailyMetricsJob` (11.3.5) to fill `SendAnalyticsReportMail`.
 */
final class GetDailyDigestUseCase
{
    private const ARTICLE_DIMENSION_PREFIX = 'article:';

    public function __construct(
        private readonly AnalyticsDailyMetricRepositoryInterface $metrics,
        private readonly AnalyticsReportDataReaderInterface $dataReader,
    ) {
    }

    public function digest(DateTimeImmutable $day): AnalyticsDigestDto
    {
        $rows = $this->metrics->rowsBetween(
            metric: null,
            dateFrom: $day,
            dateTo: $day,
        );

        return new AnalyticsDigestDto(
            day: $day,
            pageViews: $this->globalValue($rows, MetricName::PageViews),
            uniqueVisitors: $this->globalValue($rows, MetricName::UniqueVisitors),
            feedbackSubmissions: $this->globalValue($rows, MetricName::FeedbackSubmissions),
            topArticle: $this->topArticle($rows),
        );
    }

    /**
     * @param list<AnalyticsMetric> $rows
     */
    private function globalValue(array $rows, MetricName $metric): float
    {
        foreach ($rows as $row) {
            if ($row->toMetric() === $metric && $row->toDimension() === null) {
                return $row->toValue();
            }
        }

        return 0.0;
    }

    /**
     * The `views` rows with an `article:{id}` dimension ordered by
     * value desc — the first one with an EN title wins (ADR-0017).
     *
     * @param list<AnalyticsMetric> $rows
     */
    private function topArticle(array $rows): ?AnalyticsTopArticleDto
    {
        $viewsById = [];

        foreach ($rows as $row) {
            $dimension = $row->toDimension();

            if ($row->toMetric() !== MetricName::Views || $dimension === null) {
                continue;
            }

            if (! str_starts_with($dimension, self::ARTICLE_DIMENSION_PREFIX)) {
                continue;
            }

            $id = (int) substr($dimension, strlen(self::ARTICLE_DIMENSION_PREFIX));
            $viewsById[$id] = $row->toValue();
        }

        if ($viewsById === []) {
            return null;
        }

        arsort($viewsById);

        $titles = $this->dataReader->articleTitles(array_keys($viewsById));

        foreach (array_keys($viewsById) as $id) {
            if (! isset($titles[$id])) {
                continue;
            }

            return new AnalyticsTopArticleDto(
                id: $id,
                title: $titles[$id],
                views: $viewsById[$id],
            );
        }

        return null;
    }
}
