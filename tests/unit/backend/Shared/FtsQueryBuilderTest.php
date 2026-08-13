<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\Infra\Search\FtsQueryBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Mockery;

/**
 * The shared FTS expression builder (B16, database.md:651-695): the
 * safe parameterized fragments of the public blog/project search —
 * plainto_tsquery + ts_rank, the ts_headline snippet and the prefix
 * autocomplete form with the empty-tsquery CASE guard.
 */
function ftsTestBuilder(): Builder
{
    $connection = Mockery::mock(Connection::class);

    return new Builder($connection, new PostgresGrammar($connection), new PostgresProcessor());
}

describe('FtsQueryBuilder', function (): void {
    it('truncates queries to 200 characters', function (): void {
        $query = str_repeat('x', 500);
        $fts = new FtsQueryBuilder('english', $query);

        expect($fts->bindings()[1])->toBe(str_repeat('x', 200));
    });

    it('is inactive for empty and whitespace-only queries', function (): void {
        expect((new FtsQueryBuilder('english', ''))->isActive())->toBeFalse()
            ->and((new FtsQueryBuilder('english', "  \t "))->isActive())->toBeFalse()
            ->and((new FtsQueryBuilder('english', 'vuejs'))->isActive())->toBeTrue();
    });

    it('builds the plainto_tsquery filter and rank expressions', function (): void {
        $fts = new FtsQueryBuilder('english', 'payments');

        expect($fts->matches('pl.search_vector'))
            ->toBe('pl.search_vector @@ plainto_tsquery(?, ?)')
            ->and($fts->rankExpression('coalesce(pl.search_vector, pe.search_vector)'))
            ->toBe('ts_rank(coalesce(pl.search_vector, pe.search_vector), plainto_tsquery(?, ?), 32)')
            ->and($fts->bindings())->toBe(['english', 'payments']);
    });

    it('selects the rank and the headline snippet as aliased columns', function (): void {
        $fts = new FtsQueryBuilder('russian', 'шлюз');
        $query = ftsTestBuilder();

        $fts->selectRank($query, 'pl.search_vector', 'fts_rank')
            ->selectHeadline($query, 'pl.content', 'fts_snippet');

        expect($query->getBindings())->toBe([
            'russian', 'шлюз',                       // rank bindings
            'russian', 'russian', 'шлюз', 'MaxWords=50, MinWords=20, StartSel=<mark>, StopSel=</mark>',
        ])->and($query->toSql())->toContain('ts_rank(pl.search_vector, plainto_tsquery(?, ?), 32) as fts_rank')
            ->and($query->toSql())->toContain('ts_headline(?, pl.content, plainto_tsquery(?, ?), ?) as fts_snippet');
    });

    it('builds the prefix autocomplete form with the empty-tsquery CASE guard', function (): void {
        $fts = new FtsQueryBuilder('english', 'vues');

        $expression = $fts->prefixMatches('pl.search_vector');

        expect($expression)->toContain('to_tsquery(?,')
            ->and($expression)->toContain('plainto_tsquery(?, ?)::text = \'\'')
            ->and($expression)->toContain('concat(plainto_tsquery(?, ?)::text, \':*\')')
            ->and($fts->prefixBindings())->toHaveCount(7)
            ->and($fts->prefixBindings())->toBe([
                'english', 'english', 'vues', 'english', 'vues', 'english', 'vues',
            ]);
    });

    it('parameterizes the headline options', function (): void {
        $fts = new FtsQueryBuilder('english', 'x');

        expect($fts->headlineExpression('pl.content'))
            ->toBe('ts_headline(?, pl.content, plainto_tsquery(?, ?), ?)')
            ->and($fts->headlineBindings()[3])->toBe('MaxWords=50, MinWords=20, StartSel=<mark>, StopSel=</mark>');
    });
});
