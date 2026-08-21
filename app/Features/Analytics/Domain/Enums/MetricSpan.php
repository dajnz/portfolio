<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Enums;

/**
 * The report span (analytics.md:186-202, database.md:539): the
 * calendar bucketing of `analytics_reports` rows — `day` the calendar
 * day, `week` Monday-Sunday, `month` the calendar month, `year` the
 * calendar year (all in the server-local timezone), `all` the
 * whole-time single row per metric (+ dimension).
 */
enum MetricSpan: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case All = 'all';
}
