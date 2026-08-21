<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Services;

use App\Features\Analytics\App\Contracts\AnalyticsReportDataReaderInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed implementation of the cross-feature report data
 * reader (B11/B289): query-builder access inside the feature's Infra
 * layer, no Blog/Projects/Portfolio class imports. The page
 * attribution of events is no longer resolved here — the frontend
 * sends the page type + entity id with every event (B289).
 */
final class EloquentAnalyticsReportDataReader implements AnalyticsReportDataReaderInterface
{
    public function articleTitles(): array
    {
        return DB::table('blog_article_translations')
            ->where('locale', 'en')
            ->pluck('title', 'article_id')
            ->all();
    }

    public function projectTitles(): array
    {
        return DB::table('project_translations')
            ->where('locale', 'en')
            ->pluck('title', 'project_id')
            ->all();
    }

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
