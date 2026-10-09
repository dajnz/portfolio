<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Repositories;

use Illuminate\Database\Eloquent\Builder;

/**
 * Infra-layer contract for the admin cursor-paginated feedback list (ADR-0003:
 * query-builder reads live in the Infra layer — the domain repository
 * interfaces stay framework-free). Implemented by
 * {@see EloquentFeedbackRepository}.
 */
interface FeedbackListQueryRepositoryInterface
{
    /**
     * Base query for the admin cursor-paginated list.
     *
     * @return Builder<FeedbackModel>
     */
    public function listQuery(): Builder;
}
