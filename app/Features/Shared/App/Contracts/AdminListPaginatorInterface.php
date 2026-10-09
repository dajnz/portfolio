<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

use App\Features\Shared\App\DTOs\PaginationQuery;
use App\Features\Shared\App\DTOs\PaginationResultDto;

/**
 * The app-layer abstraction over the shared admin cursor pagination:
 * paginates a filtered query with the requested page/cursor/sort and
 * returns the raw rows plus the pagination facts. The implementation lives
 * on the infra layer (it drives the framework paginator); the app layer
 * never touches Eloquent builders or HTTP requests — the query is supplied
 * as a factory callable (invoked by the implementation) and the request
 * shape is rebuilt from the passed URL, so the paginator's empty-page
 * redirects keep targeting the real URL.
 */
interface AdminListPaginatorInterface
{
    /**
     * @param  callable(): \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $queryFactory
     *        Builds the filtered base query (no order, limit or offset).
     * @param  string|null  $countCacheKey  Per-filter-combination key for the cached total; null disables caching.
     */
    public function paginate(
        callable $queryFactory,
        PaginationQuery $query,
        int $perPage,
        ?string $countCacheKey,
        string $requestUrl,
    ): PaginationResultDto;
}
