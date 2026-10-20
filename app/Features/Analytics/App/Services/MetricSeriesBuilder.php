<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\Services;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Builds the chart series payload for
 * `GET /admin/analytics/series?metric=&span=`. Every span renders a
 * dense calendar window ending at the current period, chronological
 * with `period_start` date labels and zero values for the periods
 * without stored rows: daily the last 30 days, weekly the last 12
 * weeks, monthly the last 12 months, yearly the last 12 years. The
 * optional dimension reads the per-target `cta_clicks` rows.
 *
 * `span=all` is the whole-time cumulative running total. The bucket
 * (day/week/month/year) is the finest one whose full history stays
 * within the point limit, anchored on the metric-wide oldest day so
 * every dimension of a multi-series chart shares one resolution and
 * one label sequence.
 */
final class MetricSeriesBuilder
{
    private const DAY_WINDOW = 30;
    private const WEEK_WINDOW = 12;
    private const MONTH_WINDOW = 12;
    private const YEAR_WINDOW = 12;
    private const CUMULATIVE_POINT_LIMIT = 30;

    public function __construct(
        private readonly AnalyticsReportRepositoryInterface $reports,
    ) {
    }

    /**
     * @return array{points: list<array{label: string, value: float}>}
     */
    public function series(
        MetricName $metric,
        MetricSpan $span,
        ?string $dimension = null,
        ?DateTimeImmutable $now = null,
    ): array {
        $today = ($now ?? new DateTimeImmutable('today'))->setTime(0, 0);

        if ($span === MetricSpan::All) {
            return ['points' => $this->cumulativePoints($metric, $dimension, $today)];
        }

        return ['points' => $this->windowPoints($metric, $span, $dimension, $today)];
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function windowPoints(
        MetricName $metric,
        MetricSpan $span,
        ?string $dimension,
        DateTimeImmutable $today,
    ): array {
        $starts = $this->windowStarts($span, $today);
        $values = $this->valuesByDate($this->reports->series($metric, $span, $starts[0], $dimension));

        return array_map(
            static fn (DateTimeImmutable $start): array => [
                'label' => $start->format('Y-m-d'),
                'value' => $values[$start->format('Y-m-d')] ?? 0.0,
            ],
            $starts,
        );
    }

    /**
     * @return list<array{label: string, value: float}>
     */
    private function cumulativePoints(MetricName $metric, ?string $dimension, DateTimeImmutable $today): array
    {
        $oldest = $this->reports->oldestDayStart($metric);

        if ($oldest === null) {
            return [];
        }

        $bucket = $this->cumulativeBucket($oldest, $today);
        $starts = $this->bucketRange($bucket, $this->bucketStart($bucket, $oldest), $this->bucketStart($bucket, $today));
        $buckets = $this->bucketSums($bucket, $this->reports->daySeries($metric, $dimension));

        $running = 0.0;
        $points = [];

        foreach ($starts as $start) {
            $key = $start->format('Y-m-d');
            $running += $buckets[$key] ?? 0.0;
            $points[] = ['label' => $key, 'value' => $running];
        }

        return $points;
    }

    /**
     * @param list<AnalyticsReport> $rows
     *
     * @return array<string, float>
     */
    private function valuesByDate(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            $values[$row->toPeriodStart()->format('Y-m-d')] = $row->toValue();
        }

        return $values;
    }

    /**
     * @return non-empty-list<DateTimeImmutable>
     */
    private function windowStarts(MetricSpan $span, DateTimeImmutable $today): array
    {
        $count = match ($span) {
            MetricSpan::Day => self::DAY_WINDOW,
            MetricSpan::Week => self::WEEK_WINDOW,
            MetricSpan::Month => self::MONTH_WINDOW,
            MetricSpan::Year => self::YEAR_WINDOW,
            default => throw new InvalidArgumentException(
                sprintf('The [%s] span is not a fixed window.', $span->value),
            ),
        };

        $last = $this->bucketStart($span, $today);

        return $this->bucketRange($span, $this->stepBackwards($span, $last, $count - 1), $last);
    }

    private function cumulativeBucket(DateTimeImmutable $oldest, DateTimeImmutable $today): MetricSpan
    {
        foreach ([MetricSpan::Day, MetricSpan::Week, MetricSpan::Month] as $bucket) {
            $first = $this->bucketStart($bucket, $oldest);
            $last = $this->bucketStart($bucket, $today);

            if ($this->bucketCount($bucket, $first, $last) <= self::CUMULATIVE_POINT_LIMIT) {
                return $bucket;
            }
        }

        return MetricSpan::Year;
    }

    /**
     * @param list<AnalyticsReport> $rows
     *
     * @return array<string, float>
     */
    private function bucketSums(MetricSpan $bucket, array $rows): array
    {
        $sums = [];

        foreach ($rows as $row) {
            $key = $this->bucketStart($bucket, $row->toPeriodStart())->format('Y-m-d');
            $sums[$key] = ($sums[$key] ?? 0.0) + $row->toValue();
        }

        return $sums;
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function bucketRange(MetricSpan $bucket, DateTimeImmutable $first, DateTimeImmutable $last): array
    {
        $starts = [];
        $cursor = $first;

        while ($cursor <= $last) {
            $starts[] = $cursor;
            $cursor = $this->nextBucket($bucket, $cursor);
        }

        return $starts;
    }

    private function bucketCount(MetricSpan $bucket, DateTimeImmutable $first, DateTimeImmutable $last): int
    {
        $days = (int) $first->diff($last)->days;

        return match ($bucket) {
            MetricSpan::Day => $days + 1,
            MetricSpan::Week => intdiv($days, 7) + 1,
            MetricSpan::Month => $this->monthsBetween($first, $last) + 1,
            MetricSpan::Year => (int) $last->format('Y') - (int) $first->format('Y') + 1,
            default => throw new InvalidArgumentException(
                sprintf('The [%s] span is not a bucket.', $bucket->value),
            ),
        };
    }

    private function monthsBetween(DateTimeImmutable $first, DateTimeImmutable $last): int
    {
        return ((int) $last->format('Y') - (int) $first->format('Y')) * 12
            + ((int) $last->format('n') - (int) $first->format('n'));
    }

    private function bucketStart(MetricSpan $bucket, DateTimeImmutable $date): DateTimeImmutable
    {
        $day = $date->setTime(0, 0);

        return match ($bucket) {
            MetricSpan::Day => $day,
            MetricSpan::Week => $day->modify(sprintf('-%d days', (int) $day->format('N') - 1)),
            MetricSpan::Month => $day->modify('first day of this month'),
            MetricSpan::Year => $day->setDate((int) $day->format('Y'), 1, 1),
            default => throw new InvalidArgumentException(
                sprintf('The [%s] span is not a bucket.', $bucket->value),
            ),
        };
    }

    private function stepBackwards(MetricSpan $bucket, DateTimeImmutable $start, int $buckets): DateTimeImmutable
    {
        $cursor = $start;

        for ($index = 0; $index < $buckets; $index++) {
            $cursor = $this->previousBucket($bucket, $cursor);
        }

        return $cursor;
    }

    private function nextBucket(MetricSpan $bucket, DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->shiftBucket($bucket, $date, 1);
    }

    private function previousBucket(MetricSpan $bucket, DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->shiftBucket($bucket, $date, -1);
    }

    private function shiftBucket(MetricSpan $bucket, DateTimeImmutable $date, int $direction): DateTimeImmutable
    {
        return match ($bucket) {
            MetricSpan::Day => $date->modify(sprintf('%+d day', $direction)),
            MetricSpan::Week => $date->modify(sprintf('%+d days', $direction * 7)),
            MetricSpan::Month => $date->modify(sprintf('%+d month', $direction)),
            MetricSpan::Year => $date->modify(sprintf('%+d year', $direction)),
            default => throw new InvalidArgumentException(
                sprintf('The [%s] span is not a bucket.', $bucket->value),
            ),
        };
    }
}
