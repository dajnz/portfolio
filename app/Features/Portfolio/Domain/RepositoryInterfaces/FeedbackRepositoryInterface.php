<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\RepositoryInterfaces;

use App\Features\Portfolio\Domain\Entities\Feedback;
use Illuminate\Database\Eloquent\Builder;

/**
 * Persistence of `feedback` submissions (database.md:462-480). Rows are
 * immutable apart from the read/forwarded flags.
 */
interface FeedbackRepositoryInterface
{
    public function findById(int $id): ?Feedback;

    public function store(Feedback $feedback): void;

    public function markRead(int $id): void;

    public function markForwarded(int $id): void;

    public function delete(int $id): void;

    /**
     * Base query for the admin cursor-paginated list (B11: query-builder
     * reads inside the feature's Infra layer).
     */
    public function listQuery(): Builder;
}
