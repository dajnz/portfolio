<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Persistence;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * The ordering strategy of an admin list page: the sort column, its direction,
 * and the mandatory unique tiebreaker (the primary key) that makes the ordering
 * deterministic even with duplicate sort values.
 *
 * Owns the translation of the sort into SQL: the ORDER BY clause, the keyset
 * seek predicate (WHERE ...), and the index-only positioning select used for
 * page jumps. Column names are whitelist-validated upstream and reach the SQL
 * only as grammar-quoted identifiers via the query builder — never as raw
 * interpolated strings. A sort column may instead carry a trusted SQL
 * expression configured by the feature (e.g. `coalesce(published_at,
 * created_at)`); it is never sourced from request input.
 */
final readonly class SortSpec
{
    /**
     * @param  string|null  $sortExpression  Trusted SQL expression used instead of the
     *                                       plain sort column in ORDER BY and the keyset
     *                                       predicates. Supplied by configuration — never
     *                                       from request input.
     */
    public function __construct(
        public string $sortColumn,
        public string $sortDirection,
        public string $tiebreakerColumn,
        public ?string $sortExpression = null,
    ) {
        if ($sortColumn === '') {
            throw new InvalidArgumentException('Sort spec requires a non-empty sort column.');
        }

        if ($tiebreakerColumn === '') {
            throw new InvalidArgumentException('Sort spec requires a non-empty tiebreaker column.');
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException(
                sprintf("Invalid sort direction '%s'; expected 'asc' or 'desc'.", $sortDirection),
            );
        }

        if ($sortExpression !== null && trim($sortExpression) === '') {
            throw new InvalidArgumentException('Sort spec expression must not be blank.');
        }
    }

    /**
     * Whether the sort column is the tiebreaker itself — the simple
     * single-column seek applies, no composite row-value predicate.
     */
    public function isIdSort(): bool
    {
        return $this->sortExpression === null && $this->sortColumn === $this->tiebreakerColumn;
    }

    /**
     * Comparison operator of the keyset predicate: rows following the
     * cursor in the sort order use `<` (desc) / `>` (asc); `before`
     * selects the side preceding the cursor (previous-page requests and
     * rank counts); `inclusive` turns the operator into `<=`/`>=`
     * (rank counting).
     */
    public function seekOperator(bool $before, bool $inclusive): string
    {
        $operator = $this->sortDirection === 'desc'
            ? '<'
            : '>';

        if ($before) {
            $operator = $operator === '<'
                ? '>'
                : '<';
        }

        return $inclusive
            ? $operator.'='
            : $operator;
    }

    /**
     * Applies the ORDER BY clause: the sort column (or its configured
     * expression) first, the tiebreaker second — both in the sort
     * direction.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public function applyOrderBy(EloquentBuilder|QueryBuilder $query): void
    {
        if ($this->sortExpression !== null) {
            $query->reorder()
                ->orderByRaw($this->sortExpression.' '.$this->sortDirection)
                ->orderBy($this->tiebreakerColumn, $this->sortDirection);

            return;
        }

        $query->reorder($this->sortColumn, $this->sortDirection)
            ->orderBy($this->tiebreakerColumn, $this->sortDirection);
    }

    /**
     * Applies the keyset seek predicate for the given cursor.
     *
     * Id sort: `id {operator} :cursorId`. Composite sort: the row-value
     * comparison rewritten as an equivalent parameterized where group —
     * `sort_col {columnOperator} :sortValue OR (sort_col = :sortValue AND id {operator} :cursorId)`
     * — where the column disjunct uses the strict form of the operator
     * (`<=`/`>=` become `<`/`>`), mirroring PostgreSQL's row-value
     * comparison semantics.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public function applySeek(EloquentBuilder|QueryBuilder $query, Cursor $cursor, string $operator): void
    {
        if ($this->isIdSort()) {
            $query->where($this->tiebreakerColumn, $operator, $cursor->id);

            return;
        }

        $columnOperator = match ($operator) {
            '<=' => '<',
            '>=' => '>',
            default => $operator,
        };

        $query->where(function (EloquentBuilder|QueryBuilder $group) use ($cursor, $operator, $columnOperator): void {
            $this->applySortValueComparison($group, $columnOperator, $cursor->sortValue);

            $group->orWhere(function (EloquentBuilder|QueryBuilder $tie) use ($cursor, $operator): void {
                $this->applySortValueComparison($tie, '=', $cursor->sortValue);
                $tie->where($this->tiebreakerColumn, $operator, $cursor->id);
            });
        });
    }

    /**
     * Applies the sort-value comparison on the configured expression or
     * the plain column.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    private function applySortValueComparison(
        EloquentBuilder|QueryBuilder $query,
        string $operator,
        mixed $value,
    ): void {
        if ($this->sortExpression !== null) {
            $query->whereRaw($this->sortExpression.' '.$operator.' ?', [$value]);

            return;
        }

        $query->where($this->sortColumn, $operator, $value);
    }

    /**
     * Applies the index-only positioning select for page jumps: the
     * tiebreaker plus (for composite sorts) the sort column or its
     * configured expression, aliased so the hydrated row exposes
     * `cursor_id` / `cursor_sort_value`.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public function applyPositioningSelect(EloquentBuilder|QueryBuilder $query): void
    {
        $query->select([$this->tiebreakerColumn.' as cursor_id']);

        if ($this->isIdSort()) {
            return;
        }

        if ($this->sortExpression !== null) {
            $query->selectRaw($this->sortExpression.' as cursor_sort_value');

            return;
        }

        $query->addSelect($this->sortColumn.' as cursor_sort_value');
    }
}
