<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Persistence;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * The ordering strategy of an admin list page: the sort column, its
 * direction, and the mandatory unique tiebreaker (the primary key) that
 * makes the ordering deterministic even with duplicate sort values
 * (admin-section-ui.md:187-194).
 *
 * Owns the translation of the sort into SQL: the ORDER BY clause, the
 * keyset seek predicate (WHERE ...), and the index-only positioning
 * select used for page jumps. Column names are whitelist-validated
 * upstream and reach the SQL only as grammar-quoted identifiers via the
 * query builder — never as raw interpolated strings.
 */
final readonly class SortSpec
{
    public function __construct(
        public string $sortColumn,
        public string $sortDirection,
        public string $tiebreakerColumn,
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
    }

    /**
     * Whether the sort column is the tiebreaker itself — the simple
     * single-column seek applies, no composite row-value predicate.
     */
    public function isIdSort(): bool
    {
        return $this->sortColumn === $this->tiebreakerColumn;
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
     * Applies the ORDER BY clause: the sort column first, the
     * tiebreaker second — both in the sort direction.
     *
     * @param  EloquentBuilder|QueryBuilder  $query
     */
    public function applyOrderBy(EloquentBuilder|QueryBuilder $query): void
    {
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
            $group->where($this->sortColumn, $columnOperator, $cursor->sortValue)
                ->orWhere(function (EloquentBuilder|QueryBuilder $tie) use ($cursor, $operator): void {
                    $tie->where($this->sortColumn, '=', $cursor->sortValue)
                        ->where($this->tiebreakerColumn, $operator, $cursor->id);
                });
        });
    }

    /**
     * Index-only positioning select for page jumps: the tiebreaker plus
     * (for composite sorts) the sort column, aliased so the hydrated
     * row exposes `cursor_id` / `cursor_sort_value`. The aliases are
     * regular column identifiers quoted by the grammar (no raw SQL).
     *
     * @return string[]
     */
    public function positioningSelect(): array
    {
        $columns = [$this->tiebreakerColumn.' as cursor_id'];

        if (! $this->isIdSort()) {
            $columns[] = $this->sortColumn.' as cursor_sort_value';
        }

        return $columns;
    }
}
