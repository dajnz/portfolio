<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\Contracts;

/**
 * App-layer contract for the admin cursor-paginated contacts list. The
 * `listQuery()` base query is consumed through the shared app-layer
 * paginator, so its framework builder is typed as `mixed` at this
 * boundary; implemented by the Eloquent contact repository on the Infra
 * layer.
 */
interface ContactListQueryRepositoryInterface
{
    /**
     * Base query for the admin cursor-paginated list, with the
     * translations eager-loaded for the list hydration.
     *
     * @return mixed  The base query (framework builder).
     */
    public function listQuery(): mixed;
}
