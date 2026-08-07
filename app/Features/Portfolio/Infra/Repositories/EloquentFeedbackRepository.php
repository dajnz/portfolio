<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Repositories;

use App\Features\Portfolio\Domain\Entities\Feedback;
use App\Features\Portfolio\Domain\RepositoryInterfaces\FeedbackRepositoryInterface;
use App\Features\Portfolio\Infra\Mappers\FeedbackMapper;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Eloquent-backed feedback repository. Flag writes (`markRead`,
 * `markForwarded`) touch only the flag column — the message content is
 * immutable (database.md:462-480).
 */
final class EloquentFeedbackRepository implements FeedbackRepositoryInterface
{
    public function __construct(
        private readonly FeedbackMapper $mapper,
    ) {
    }

    public function findById(int $id): ?Feedback
    {
        $model = FeedbackModel::query()->find($id);

        return $model === null
            ? null
            : $this->mapper->toEntity($model);
    }

    public function store(Feedback $feedback): void
    {
        FeedbackModel::query()->create($this->mapper->toRow($feedback));
    }

    public function markRead(int $id): void
    {
        FeedbackModel::query()->whereKey($id)->update(['is_read' => true]);
    }

    public function markForwarded(int $id): void
    {
        FeedbackModel::query()->whereKey($id)->update(['is_forwarded' => true]);
    }

    public function delete(int $id): void
    {
        FeedbackModel::query()->whereKey($id)->delete();
    }

    public function listQuery(): Builder
    {
        return FeedbackModel::query();
    }
}
