<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\Contracts;

use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use DateTimeImmutable;

/**
 * The cross-feature data the reports need (B11/B289): the EN titles
 * for the article/project tables (the page attribution itself comes
 * from the frontend's page context now — the URL lookup is gone) and
 * the feedback submission timestamps. Implemented on the Infra layer;
 * the use case depends on this interface only.
 */
interface AnalyticsReportDataReaderInterface
{
    /** @return array<int, string> article id → EN title */
    public function articleTitles(): array;

    /** @return array<int, string> project id → EN title */
    public function projectTitles(): array;

    /** @return list<DateTimeImmutable> */
    public function feedbackSubmissions(ReportPeriod $period): array;
}
