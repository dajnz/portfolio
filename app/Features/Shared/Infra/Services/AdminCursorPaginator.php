<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\Infra\Persistence\Cursor;
use App\Features\Shared\Infra\Persistence\CursorDecoder;
use App\Features\Shared\Infra\Persistence\CursorMap;
use App\Features\Shared\Infra\Persistence\CursorPage;
use App\Features\Shared\Infra\Persistence\PaginationRequest;
use App\Features\Shared\Infra\Persistence\SortSpec;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Server-side keyset (cursor) pagination for the seven admin list pages
 * (admin-section-ui.md:124-210, step 2.8).
 *
 * Request forms:
 * - `?page=N` — first visit or a direct page-jump; the cursor of the
 *   last item of page N-1 is resolved via an index-only
 *   keyset-positioning subquery.
 * - `?cursor[id]=<id>&cursor[sort_value]=<val>` — the page following
 *   the cursor item in the sort order (next navigation); adding
 *   `&cursor[before]=1` requests the preceding page (previous
 *   navigation, per B72).
 * - `?sort_column=` / `?sort_direction=` — active sort (default
 *   `id` / `desc` per admin-section-ui.md:185), whitelist-validated.
 *
 * The `SELECT COUNT(*)` per filter combination is cached in Redis for
 * 30 s (admin-section-ui.md:149); the count is only used for the
 * page-number widget. An incoming cursor whose shape mismatches the
 * active sort falls back to a page-1 request with a warning log
 * (admin-section-ui.md:196). SQL translation (ORDER BY, seek predicate,
 * positioning select) is delegated to SortSpec; cursor parsing and
 * validation to CursorDecoder; input extraction to PaginationRequest.
 */
final class AdminCursorPaginator
{
    private const COUNT_CACHE_TTL_SECONDS = 30;

    private const COUNT_CACHE_KEY_PREFIX = 'admin_cursor_count:';

    /**
     * @param  string[]  $allowedSortColumns  Columns the `sort_column` request param may select
     *                                        beyond the id column (which is always allowed).
     * @param  string  $idColumn  Primary-key column, qualified (`projects.id`) when the query joins.
     */
    public function __construct(
        private readonly CursorDecoder $cursorDecoder = new CursorDecoder(),
        private readonly array $allowedSortColumns = [],
        private readonly string $idColumn = 'id',
    ) {
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query  Filtered base query; no order, limit or offset.
     * @param  string|null  $countCacheKey  Per-filter-combination key for the cached total; null disables caching.
     */
    public function paginate(
        EloquentBuilder|QueryBuilder $query,
        Request $request,
        int $perPage,
        ?string $countCacheKey = null,
    ): CursorPage {
        $perPage = max(1, $perPage);
        $pagination = PaginationRequest::fromRequest($request, $this->allowedSortColumns, $this->idColumn);
        $total = $this->countTotal(clone $query, $countCacheKey);

        if ($pagination->hasCursorParams()) {
            $cursor = $this->cursorDecoder->decode($pagination->cursorParams, $pagination->sorting);

            if ($cursor !== null) {
                $pageNumber = $this->pageContainingCursor($query, $cursor, $pagination->sorting, $perPage);

                return $this->fetchAndWrap(
                    $query,
                    $cursor,
                    $pagination->before,
                    $pagination->sorting,
                    $perPage,
                    $total,
                    max(1, $pageNumber + ($pagination->before ? -1 : 1)),
                );
            }

            // Mismatched cursor — the decoder logged the warning; page 1.
            return $this->fetchAndWrap($query, null, false, $pagination->sorting, $perPage, $total, 1);
        }

        if ($pagination->page === 1) {
            return $this->fetchAndWrap($query, null, false, $pagination->sorting, $perPage, $total, 1);
        }

        $cursor = $this->positionCursorForPage($query, $pagination->page, $perPage, $pagination->sorting);

        if ($cursor === null) {
            return CursorPage::empty($total, $perPage, $pagination->page, $pagination->sorting);
        }

        return $this->fetchAndWrap($query, $cursor, false, $pagination->sorting, $perPage, $total, $pagination->page);
    }

    /**
     * Total matching rows; the count is cached per filter combination
     * (30 s TTL) when a cache key is provided.
     *
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private function countTotal(EloquentBuilder|QueryBuilder $query, ?string $countCacheKey): int
    {
        $countQuery = clone $query;

        if ($countCacheKey === null) {
            return (int) $countQuery->count();
        }

        return (int) Cache::remember(
            self::COUNT_CACHE_KEY_PREFIX.$countCacheKey,
            self::COUNT_CACHE_TTL_SECONDS,
            static fn (): int => (int) $countQuery->count(),
        );
    }

    /**
     * 1-based page number containing the cursor item in the filtered,
     * sorted dataset (the source of `current_page` for cursor
     * requests). Derived from the cursor's rank — an index-only count
     * of rows sorting at-or-before it.
     *
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private function pageContainingCursor(
        EloquentBuilder|QueryBuilder $query,
        Cursor $cursor,
        SortSpec $sorting,
        int $perPage,
    ): int {
        $rankQuery = clone $query;
        $sorting->applySeek($rankQuery, $cursor, $sorting->seekOperator(before: true, inclusive: true));

        return intdiv((int) $rankQuery->count() - 1, $perPage) + 1;
    }

    /**
     * Resolves the cursor for the last item of the page preceding
     * `$page` via an index-only keyset-positioning subquery
     * (admin-section-ui.md:151-161). Returns null when `$page` lies
     * beyond the last page.
     *
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private function positionCursorForPage(
        EloquentBuilder|QueryBuilder $query,
        int $page,
        int $perPage,
        SortSpec $sorting,
    ): ?Cursor {
        $offset = ($page - 1) * $perPage - 1;

        if ($offset < 0) {
            return null;
        }

        $positioning = clone $query;
        $sorting->applyOrderBy($positioning);

        $row = $positioning->select($sorting->positioningSelect())
            ->offset($offset)
            ->limit(1)
            ->first();

        if ($row === null) {
            return null;
        }

        return new Cursor(
            id: (int) $this->rowAttribute($row, 'cursor_id'),
            sortValue: $sorting->isIdSort()
                ? null
                : $this->rowAttribute($row, 'cursor_sort_value'),
        );
    }

    /**
     * Applies the sort order and the keyset predicate (inverted for
     * previous-page requests) and wraps the fetched page into the
     * response shape.
     *
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private function fetchAndWrap(
        EloquentBuilder|QueryBuilder $query,
        ?Cursor $cursor,
        bool $before,
        SortSpec $sorting,
        int $perPage,
        int $total,
        int $currentPage,
    ): CursorPage {
        $sorting->applyOrderBy($query);

        if ($cursor !== null) {
            $sorting->applySeek($query, $cursor, $sorting->seekOperator(before: $before, inclusive: false));
        }

        $items = $query->limit($perPage)->get()->all();

        return new CursorPage(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $currentPage,
            sorting: $sorting,
            cursors: new CursorMap(
                first: $items === [] ? null : $this->toCursor($items[0], $sorting),
                last: $items === [] ? null : $this->toCursor($items[count($items) - 1], $sorting),
                page: $currentPage,
            ),
        );
    }

    /**
     * Builds the response cursor of a page item (null for an empty
     * page). Qualified column names are read from their last segment, so
     * `projects.published_at` maps to the model's `published_at`
     * attribute.
     */
    private function toCursor(mixed $item, SortSpec $sorting): ?Cursor
    {
        if ($item === null) {
            return null;
        }

        return new Cursor(
            id: (int) $this->rowAttribute($item, $this->unqualifiedColumn($sorting->tiebreakerColumn)),
            sortValue: $sorting->isIdSort()
                ? null
                : $this->rowAttribute($item, $this->unqualifiedColumn($sorting->sortColumn)),
        );
    }

    /**
     * Reads a column from a hydrated row — an Eloquent model or a raw
     * stdClass from a query-builder result.
     */
    private function rowAttribute(mixed $row, string $attribute): mixed
    {
        return $row instanceof Model
            ? $row->getAttribute($attribute)
            : ($row->{$attribute} ?? null);
    }

    private function unqualifiedColumn(string $column): string
    {
        $position = strrpos($column, '.');

        return $position === false
            ? $column
            : substr($column, $position + 1);
    }
}
