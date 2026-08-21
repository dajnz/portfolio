<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Services;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed implementation of the cross-feature analytics data
 * reader (B11/B292): query-builder access inside the feature's Infra
 * layer, no Portfolio class imports. The `feedback` rows are never
 * pruned, so the `feedback_submissions` metric can be rebuilt for the
 * full history. The half-open range mirrors the event repository
 * (`>= start AND < end+1s` — ReportPeriod `toPeriodEndExclusive`).
 */
final class EloquentAnalyticsReportDataReader implements AnalyticsReportDataReaderInterface
{
    public function feedbackSubmissions(ReportPeriod $period): array
    {
        return DB::table('feedback')
            ->where('created_at', '>=', $period->toPeriodStart())
            ->where('created_at', '<', $period->toPeriodEndExclusive())
            ->pluck('created_at')
            ->map(static fn ($createdAt): DateTimeImmutable => new DateTimeImmutable((string) $createdAt))
            ->all();
    }
}
