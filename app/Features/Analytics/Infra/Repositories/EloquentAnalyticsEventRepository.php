<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Repositories;

use App\Features\Analytics\Domain\Entities\AnalyticsEvent;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ReportPeriod;
use App\Features\Analytics\Infra\Mappers\AnalyticsEventMapper;
use App\Features\Analytics\Infra\Models\AnalyticsEventModel;
use DateTimeImmutable;

/**
 * Eloquent-backed raw event storage. The period read uses a half-open
 * `created_at` range (`>= start`, `< end+1s`) so microsecond-
 * timestamped events at 23:59:59.xxx are not dropped (ReportPeriod
 * `toPeriodEndExclusive`); `Query\Builder::insert` skips the model's
 * casts, so the jsonb `metadata` column is encoded here (`created_at`
 * is left to the DB default).
 */
final class EloquentAnalyticsEventRepository implements AnalyticsEventRepositoryInterface
{
    public function __construct(
        private readonly AnalyticsEventMapper $mapper,
    ) {
    }

    public function insertBatch(array $events): void
    {
        if ($events === []) {
            return;
        }

        $rows = array_map(function (AnalyticsEvent $event): array {
            $row = $this->mapper->toRow($event);

            if ($row['metadata'] !== null) {
                $row['metadata'] = json_encode(
                    $row['metadata'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );
            }

            return $row;
        }, $events);

        AnalyticsEventModel::query()->insert($rows);
    }

    public function eventsBetween(ReportPeriod $period): array
    {
        return AnalyticsEventModel::query()
            ->where('created_at', '>=', $period->toPeriodStart())
            ->where('created_at', '<', $period->toPeriodEndExclusive())
            ->get()
            ->map(fn (AnalyticsEventModel $model): ?AnalyticsEvent => $this->mapper->toEntity($model))
            ->filter()
            ->values()
            ->all();
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        return AnalyticsEventModel::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
