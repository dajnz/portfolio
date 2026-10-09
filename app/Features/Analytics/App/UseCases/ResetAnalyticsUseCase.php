<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\DTOs\AnalyticsWipeResultDto;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsDailyMetricRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;

/**
 * The `analytics:wipe` backing: clears ALL raw events, daily metrics
 * and reports so manual testing can start from a fresh state. The
 * reports are transient (rebuilt from metrics on the next generation),
 * the metrics are the long-term store — a wipe is deliberate and
 * destructive, hence the command's confirmation prompt. The per-table
 * deleted counts come back as the result DTO.
 */
final class ResetAnalyticsUseCase
{
    public function __construct(
        private readonly AnalyticsEventRepositoryInterface $events,
        private readonly AnalyticsDailyMetricRepositoryInterface $metrics,
        private readonly AnalyticsReportRepositoryInterface $reports,
    ) {
    }

    public function wipe(): AnalyticsWipeResultDto
    {
        return new AnalyticsWipeResultDto(
            events: $this->events->deleteAll(),
            metrics: $this->metrics->deleteAll(),
            reports: $this->reports->deleteAll(),
        );
    }
}
