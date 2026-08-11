<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Persistence;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Validated inputs of an admin list pagination request: the requested
 * page number, the raw cursor params (`?cursor[id]=&cursor[sort_value]=`
 * with an optional `cursor[before]=1` marker for the previous page), and
 * the whitelist-resolved sort spec.
 *
 * Built from the HTTP request by the paginator; a `sort_column` that is
 * neither the id column nor a whitelisted column falls back to the id
 * sort with a warning (admin-section-ui.md:196).
 */
final readonly class PaginationRequest
{
    /**
     * @param  array<string, mixed>|null  $cursorParams
     */
    private function __construct(
        public int $page,
        public ?array $cursorParams,
        public bool $before,
        public SortSpec $sorting,
    ) {
    }

    /**
     * @param  string[]  $allowedSortColumns  Columns the `sort_column` request param may select
     *                                        beyond the id column (which is always allowed).
     * @param  string|null  $defaultSortColumn  Sort column used when the request carries no
     *                                          `sort_column` (default: the id column, B72).
     */
    public static function fromRequest(Request $request, array $allowedSortColumns, string $idColumn, ?string $defaultSortColumn = null): self
    {
        $sortColumn = trim((string) $request->query('sort_column', ''));

        if ($sortColumn === '') {
            $sortColumn = $defaultSortColumn ?? $idColumn;
        } elseif ($sortColumn !== $idColumn && ! in_array($sortColumn, $allowedSortColumns, true)) {
            Log::warning('Admin list sort_column rejected: column is not whitelisted; falling back to id sort.', [
                'sort_column' => $sortColumn,
            ]);

            $sortColumn = $idColumn;
        }

        $sortDirection = strtolower((string) $request->query('sort_direction', '')) === 'asc'
            ? 'asc'
            : 'desc';

        $cursorParams = $request->input('cursor');

        return new self(
            page: max(1, $request->integer('page', 1)),
            cursorParams: is_array($cursorParams) ? $cursorParams : null,
            before: self::beforeFromParams($cursorParams),
            sorting: new SortSpec(
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
                tiebreakerColumn: $idColumn,
            ),
        );
    }

    public function hasCursorParams(): bool
    {
        return $this->cursorParams !== null;
    }

    private static function beforeFromParams(mixed $params): bool
    {
        if (! is_array($params)) {
            return false;
        }

        $before = $params['before'] ?? null;

        return $before === '1' || $before === 'true' || $before === 1 || $before === true;
    }
}
