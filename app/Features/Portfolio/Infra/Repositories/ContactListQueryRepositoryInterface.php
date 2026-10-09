<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Repositories;

use Illuminate\Database\Eloquent\Builder;

/**
 * Infra-layer contract for the admin cursor-paginated contacts list (ADR-0003:
 * query-builder reads live in the Infra layer — the domain repository
 * interfaces stay framework-free). Implemented by
 * {@see EloquentContactRepository}.
 */
interface ContactListQueryRepositoryInterface
{
    /**
     * Base query for the admin cursor-paginated list, with the
     * translations eager-loaded for the list hydration.
     *
     * @return Builder<ContactModel>
     */
    public function listQuery(): Builder;
}
