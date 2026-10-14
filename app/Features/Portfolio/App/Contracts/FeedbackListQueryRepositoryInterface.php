<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\Contracts;

/**
 * App-layer contract for the admin cursor-paginated feedback list. The
 * `listQuery()` base query is consumed through the shared app-layer
 * paginator, so its framework builder is typed as `mixed` at this
 * boundary; implemented by the Eloquent feedback repository on the Infra
 * layer.
 */
interface FeedbackListQueryRepositoryInterface
{
    /**
     * Base query for the admin cursor-paginated list.
     *
     * @return mixed  The base query (framework builder).
     */
    public function listQuery(): mixed;
}
