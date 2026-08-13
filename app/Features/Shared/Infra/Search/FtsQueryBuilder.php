<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Search;

use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The shared PostgreSQL full-text search expression builder (B16,
 * database.md:603-695) — the safe SQL fragments used by the public
 * blog/project list queries: `search()` (plainto_tsquery + ts_rank
 * with the 32 normalization, no setweight), `headline()` (ts_headline
 * snippets with the database.md:676-683 `<mark>` parameters) and
 * `autocomplete()` (the prefix form with the empty-tsquery CASE
 * guard, database.md:662-675).
 *
 * The query is truncated to 200 characters before any expression is
 * built (database.md:694); an empty query produces NO fragments —
 * the caller skips FTS entirely and returns all rows (database.md:
 * 691). All expressions are fully parameterized (the dictionary and
 * the user query travel as bindings), so user input can never inject
 * SQL.
 *
 * Callers compose the fragments against their locale-resolved
 * `search_vector` / `content` expressions, e.g.:
 *
 *     $fts = new FtsQueryBuilder($dictionary, $search);
 *     $fts->selectRank($query, 'coalesce(pl.search_vector, pe.search_vector)')
 *         ->selectHeadline($query, 'coalesce(pl.content, pe.content)')
 *         ->whereRaw($fts->matches('coalesce(pl.search_vector, pe.search_vector)'), $fts->bindings());
 */
final class FtsQueryBuilder
{
    private const MAX_QUERY_LENGTH = 200;

    private const HEADLINE_OPTIONS = 'MaxWords=50, MinWords=20, StartSel=<mark>, StopSel=</mark>';

    /**
     * The whitelist of the interpolated column expressions: a plain
     * column path (`pl.search_vector`) or a `coalesce()` call over
     * such paths — nothing else. No operators, quotes, semicolons,
     * comments, string literals or NESTED parens (a subquery like
     * `(select ...)` is structurally impossible). The user query and
     * the dictionary ALWAYS travel as bound parameters; this guard
     * makes it impossible to smuggle input through the expression
     * arguments of the fragments.
     */
    private const EXPRESSION_PATTERN = '/^(?:[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?'
        .'|coalesce\([a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?'
        .'(,[ ]?[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?)*\))$/';

    private readonly string $query;

    public function __construct(
        private readonly string $dictionary,
        string $query,
    ) {
        $this->query = mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH);
    }

    /**
     * Whether any FTS fragment should be applied at all — false for
     * empty/whitespace-only queries ("return all results", database.md:
     * 691).
     */
    public function isActive(): bool
    {
        return $this->query !== '';
    }

    /**
     * The `plainto_tsquery(?, ?)` expression — the safe tokenization
     * of arbitrary user input (database.md:651-658).
     */
    public function plainExpression(): string
    {
        return 'plainto_tsquery(?, ?)';
    }

    /**
     * The bindings of the `plainto_tsquery` expressions:
     * `[dictionary, query]`.
     *
     * @return array{0: string, 1: string}
     */
    public function bindings(): array
    {
        return [$this->dictionary, $this->query];
    }

    /**
     * The `search()` filter — `{vector} @@ plainto_tsquery(?, ?)`.
     */
    public function matches(string $vectorExpression): string
    {
        return $this->expression($vectorExpression).' @@ '.$this->plainExpression();
    }

    /**
     * The ts_rank expression with the 32 (document-length)
     * normalization — database.md:659.
     */
    public function rankExpression(string $vectorExpression): string
    {
        return 'ts_rank('.$this->expression($vectorExpression).', '.$this->plainExpression().', 32)';
    }

    /**
     * Selects the rank as an aliased column (referenced by the ORDER
     * BY and the keyset seek expressions).
     */
    public function selectRank(QueryBuilder $query, string $vectorExpression, string $alias = 'fts_rank'): self
    {
        $query->selectRaw($this->rankExpression($vectorExpression).' as '.$alias, $this->bindings());

        return $this;
    }

    /**
     * The `headline()` snippet expression over the given content
     * column — `ts_headline(?, {content}, plainto_tsquery(?, ?),
     * 'MaxWords=50, MinWords=20, StartSel=<mark>, StopSel=</mark>')`
     * (database.md:676-683).
     */
    public function headlineExpression(string $contentExpression): string
    {
        return 'ts_headline(?, '.$this->expression($contentExpression).', '.$this->plainExpression().', ?)';
    }

    /**
     * The bindings of the headline expression — the dictionary, the
     * plainto_tsquery pair and the headline options.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    public function headlineBindings(): array
    {
        return [$this->dictionary, $this->dictionary, $this->query, self::HEADLINE_OPTIONS];
    }

    /**
     * Selects the highlighted snippet as an aliased column (the
     * Inertia HTML search results carry it — the JSON variant returns
     * raw values, B16).
     */
    public function selectHeadline(QueryBuilder $query, string $contentExpression, string $alias = 'fts_snippet'): self
    {
        $query->selectRaw($this->headlineExpression($contentExpression).' as '.$alias, $this->headlineBindings());

        return $this;
    }

    /**
     * The `autocomplete()` prefix form (database.md:662-675): the last
     * word becomes a prefix (`:*`), with the CASE guard that falls
     * back to the plain tsquery when `plainto_tsquery` produced an
     * empty tsquery (stop-word-only input — `to_tsquery('english',
     * ':*')` would be a syntax error).
     */
    public function prefixMatches(string $vectorExpression): string
    {
        $plain = $this->plainExpression();

        return $this->expression($vectorExpression).' @@ to_tsquery(?, '
            .'CASE WHEN '.$plain.'::text = \'\' '
            .'THEN '.$plain.'::text '
            .'ELSE concat('.$plain.'::text, \':*\') END)';
    }

    /**
     * The bindings of the prefix expression — the outer to_tsquery
     * dictionary followed by the three plainto_tsquery pairs.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string}
     */
    public function prefixBindings(): array
    {
        return [
            $this->dictionary,
            $this->dictionary,
            $this->query,
            $this->dictionary,
            $this->query,
            $this->dictionary,
            $this->query,
        ];
    }

    /**
     * Validates + returns the interpolated column expression — the
     * only interpolated SQL of the whole builder; everything else is
     * a bound parameter. An invalid expression is a programming
     * error (whitelist rejection, never a silent pass-through).
     */
    private function expression(string $expression): string
    {
        if (preg_match(self::EXPRESSION_PATTERN, $expression) !== 1) {
            throw new \InvalidArgumentException('FtsQueryBuilder received an invalid column expression.');
        }

        return $expression;
    }
}
