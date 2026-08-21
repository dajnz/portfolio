<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Repositories;

use App\Features\Analytics\Domain\Entities\AnalyticsReport;
use App\Features\Analytics\Domain\Factories\AnalyticsReportFactory;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsReportRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Domain\ValueObjects\ReportScalarValue;
use App\Features\Analytics\Domain\ValueObjects\ReportSeriesValue;
use App\Features\Analytics\Domain\ValueObjects\ReportTableValue;
use App\Features\Analytics\Infra\Models\AnalyticsReportModel;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed unified report storage (B288). Upserts happen
 * through the query builder (`Eloquent\Builder::upsert` does not apply
 * the model's casts, so the jsonb `data` column is encoded here) on
 * the `(report_type, period_start, period_end)` unique key —
 * re-aggregating a period overwrites `data` + `generated_at`
 * (analytics.md:186); `generated_at` is persisted from the entity.
 * The serialized results come from the entity's typed `toResults()`
 * (B289 — the entity exposes no payload accessor). Reads materialize
 * the rows through {@see AnalyticsReportFactory}.
 */
final class EloquentAnalyticsReportRepository implements AnalyticsReportRepositoryInterface
{
    public function save(AnalyticsReport $report): void
    {
        DB::table('analytics_reports')->upsert(
            [[
                'report_type' => $report->reportType(),
                'period_start' => $report->toPeriodStart()->format('Y-m-d'),
                'period_end' => $report->toPeriodEnd()->format('Y-m-d'),
                'data' => json_encode(
                    $this->serializeResults($report),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
                'generated_at' => $report->toGeneratedAt(),
            ]],
            ['report_type', 'period_start', 'period_end'],
            ['data', 'generated_at'],
        );
    }

    public function all(): array
    {
        return AnalyticsReportModel::query()
            ->orderByDesc('period_start')
            ->orderBy('report_type')
            ->get()
            ->map(fn (AnalyticsReportModel $model): ?AnalyticsReport => $this->toEntity($model))
            ->filter()
            ->values()
            ->all();
    }

    public function findNewestBefore(string $reportType, ReportPeriod $period): ?AnalyticsReport
    {
        $model = AnalyticsReportModel::query()
            ->where('report_type', $reportType)
            ->where('period_start', '<', $period->toPeriodStart()->format('Y-m-d'))
            ->orderByDesc('period_start')
            ->first();

        return $model === null
            ? null
            : $this->toEntity($model);
    }

    private function toEntity(AnalyticsReportModel $model): ?AnalyticsReport
    {
        return AnalyticsReportFactory::fromRow(
            id: $model->id,
            reportType: $model->report_type,
            period: new ReportPeriod(
                $model->period_start->toDateTimeImmutable(),
                $model->period_end->toDateTimeImmutable(),
            ),
            payload: $model->data,
            generatedAt: $model->generated_at->toDateTimeImmutable(),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function serializeResults(AnalyticsReport $report): array
    {
        return array_map(
            static fn (ReportScalarValue|ReportSeriesValue|ReportTableValue $result): array => $result->toArray(),
            $report->toResults(),
        );
    }
}
