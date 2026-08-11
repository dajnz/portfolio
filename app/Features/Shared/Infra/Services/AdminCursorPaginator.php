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
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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
 * page-number widget. The cache is versioned per entity — a write
 * (`AdminCursorPaginator::invalidateCountCache($entity)`) bumps the
 * version so the next list request re-counts immediately (B175: adding
 * or deleting items must update the paginator without a reload).
 * An incoming cursor whose shape mismatches the active sort falls back
 * to a page-1 request with a warning log (admin-section-ui.md:196).
 * SQL translation (ORDER BY, seek predicate, positioning select) is
 * delegated to SortSpec; cursor parsing and validation to
 * CursorDecoder; input extraction to PaginationRequest.
 */
final class AdminCursorPaginator
{
    private const COUNT_CACHE_TTL_SECONDS = 30;

    private const COUNT_CACHE_KEY_PREFIX = 'admin_cursor_count:';

    private const COUNT_CACHE_VERSION_PREFIX = 'admin_cursor_count_version:';

    /**
     * @param  string[]  $allowedSortColumns  Columns the `sort_column` request param may select
     *                                        beyond the id column (which is always allowed).
     * @param  string  $idColumn  Primary-key column, qualified (`projects.id`) when the query joins.
     * @param  string|null  $defaultSortColumn  Sort column used when the request carries no
     *                                          `sort_column` (default: the id column, B72).
     */
    public function __construct(
        private readonly CursorDecoder $cursorDecoder = new CursorDecoder(),
        private readonly array $allowedSortColumns = [],
        private readonly string $idColumn = 'id',
        private readonly ?string $defaultSortColumn = null,
    ) {
    }

    /**
     * Bumps the per-entity count-cache version — every write to a
     * paginated entity (store/update/destroy/restore/force) must call
     * this so the cached total reflects the change immediately
     * (B175: without it the paginator stayed stale for up to 30 s and
     * new pages / shrunk page counts appeared only after a reload).
     */
    public static function invalidateCountCache(string $entity): void
    {
        Cache::increment(self::COUNT_CACHE_VERSION_PREFIX.$entity);
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
        $pagination = PaginationRequest::fromRequest(
            $request,
            $this->allowedSortColumns,
            $this->idColumn,
            $this->defaultSortColumn,
        );
        $total = $this->countTotal(clone $query, $countCacheKey);

        if ($pagination->hasCursorParams()) {
            $cursor = $this->cursorDecoder->decode($pagination->cursorParams, $pagination->sorting);

            if ($cursor !== null) {
                $pageNumber = $this->pageContainingCursor($query, $cursor, $pagination->sorting, $perPage);
                $page = $this->fetchAndWrap(
                    $query,
                    $cursor,
                    $pagination->before,
                    $pagination->sorting,
                    $perPage,
                    $total,
                    max(1, $pageNumber + ($pagination->before ? -1 : 1)),
                );

                if ($page->items === [] && $page->total > 0) {
                    // The cursor now points past the last remaining item
                    // (its page was emptied by deletes, B175). Redirect
                    // to the page that still has content — the page
                    // containing the cursor (the one preceding the
                    // empty page; page 1 for an empty previous-page
                    // request) — instead of rendering an empty page the
                    // user cannot navigate away from.
                    $targetPage = max(1, $pageNumber + ($pagination->before ? -1 : 0));
                    $this->redirectToPage($request, $targetPage);
                }

                return $page;
            }

            // Mismatched cursor — the decoder logged the warning; page 1.
            return $this->fetchAndWrap($query, null, false, $pagination->sorting, $perPage, $total, 1);
        }

        if ($pagination->page === 1) {
            return $this->fetchAndWrap($query, null, false, $pagination->sorting, $perPage, $total, 1);
        }

        $cursor = $this->positionCursorForPage($query, $pagination->page, $perPage, $pagination->sorting);

        if ($cursor === null) {
            // The requested page lies beyond the last one (B175: e.g.
            // the only item of the last page was deleted). Redirect to
            // the last valid page — filters preserved — instead of
            // rendering an empty page the user cannot navigate away
            // from. Page 1 and empty datasets keep rendering (the
            // empty state).
            $lastPage = (int) ceil($total / $perPage);

            if ($total > 0 && $pagination->page > $lastPage) {
                $this->redirectToPage($request, $lastPage);
            }

            return CursorPage::empty($total, $perPage, $pagination->page, $pagination->sorting);
        }

        $page = $this->fetchAndWrap($query, $cursor, false, $pagination->sorting, $perPage, $total, $pagination->page);

        if ($page->items === [] && $page->total > 0) {
            // The requested page was emptied by deletes — the
            // positioning cursor (the previous page's last item) still
            // exists, so the beyond-range check above cannot fire
            // (B175: deleting the last item of the last page must not
            // strand the user on an empty page). Redirect to the
            // previous page.
            $this->redirectToPage($request, max(1, $pagination->page - 1));
        }

        return $page;
    }

    /**
     * Throws the 302 redirect to a valid list page (filters preserved).
     */
    private function redirectToPage(Request $request, int $page): never
    {
        throw new HttpResponseException(
            new RedirectResponse($this->lastValidPageUrl($request, $page), SymfonyResponse::HTTP_FOUND),
        );
    }

    /**
     * The last-valid-page URL: the request's own query minus the
     * page/cursor params, with `page` set to the target.
     */
    private function lastValidPageUrl(Request $request, int $page): string
    {
        $query = $request->query();
        unset($query['page'], $query['cursor']);
        $query['page'] = $page;

        return $request->url().'?'.http_build_query($query);
    }

    /**
     * Total matching rows; the count is cached per filter combination
     * (30 s TTL) when a cache key is provided, versioned per entity
     * (B175 — {@see self::invalidateCountCache()}).
     *
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     */
    private function countTotal(EloquentBuilder|QueryBuilder $query, ?string $countCacheKey): int
    {
        $countQuery = clone $query;

        if ($countCacheKey === null) {
            return (int) $countQuery->count();
        }

        $entity = explode(':', $countCacheKey)[0];
        $version = (int) Cache::get(self::COUNT_CACHE_VERSION_PREFIX.$entity, 0);

        return (int) Cache::remember(
            self::COUNT_CACHE_KEY_PREFIX.$countCacheKey.':v'.$version,
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
