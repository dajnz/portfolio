<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\AdminListPaginatorInterface;
use App\Features\Shared\App\DTOs\CursorDto;
use App\Features\Shared\App\DTOs\CursorMapDto;
use App\Features\Shared\App\DTOs\CursorPaginationDto;
use App\Features\Shared\App\DTOs\PaginationQuery;
use App\Features\Shared\App\DTOs\PaginationResultDto;
use App\Features\Shared\Infra\Persistence\CursorDecoder;
use Illuminate\Http\Request;

/**
 * The framework-backed {@see AdminListPaginatorInterface}: invokes the
 * query factory, rebuilds the HTTP request shape from the passed URL
 * (so the paginator's B175 empty-page redirects keep targeting the
 * real URL) and maps the paginator's page into the app-layer DTOs.
 */
final class AdminListPaginator implements AdminListPaginatorInterface
{
    /**
     * @param  string[]  $allowedSortColumns  Sort columns the `sort_column` param may select beyond the id column.
     */
    public function __construct(
        private readonly array $allowedSortColumns = [],
    ) {
    }

    public function paginate(
        callable $queryFactory,
        PaginationQuery $query,
        int $perPage,
        ?string $countCacheKey,
        string $requestUrl,
    ): PaginationResultDto {
        $paginator = new AdminCursorPaginator(
            cursorDecoder: new CursorDecoder(),
            allowedSortColumns: $this->allowedSortColumns,
        );

        $page = $paginator->paginate(
            $queryFactory(),
            Request::create($requestUrl),
            $perPage,
            $countCacheKey,
        );

        return new PaginationResultDto(
            rows: $page->items,
            pagination: new CursorPaginationDto(
                total: $page->total,
                perPage: $page->perPage,
                currentPage: $page->currentPage,
                sortColumn: $page->sorting->sortColumn,
                sortDirection: $page->sorting->sortDirection,
                cursors: new CursorMapDto(
                    first: $page->cursors->first === null
                        ? null
                        : new CursorDto(id: $page->cursors->first->id, sortValue: $page->cursors->first->sortValue),
                    last: $page->cursors->last === null
                        ? null
                        : new CursorDto(id: $page->cursors->last->id, sortValue: $page->cursors->last->sortValue),
                    page: $page->cursors->page,
                ),
            ),
        );
    }
}
