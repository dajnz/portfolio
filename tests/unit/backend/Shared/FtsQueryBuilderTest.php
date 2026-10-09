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
 * The shared FTS expression builder (ADR-0007, database.md:651-695): the
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

    it('never interpolates user input into SQL — everything is bound', function (): void {
        $malicious = "x'); DROP TABLE blog_articles; --";
        $fts = new FtsQueryBuilder('english', $malicious);

        $sql = $fts->matches('pl.search_vector').' '.$fts->rankExpression('pl.search_vector')
            .' '.$fts->headlineExpression('pl.content').' '.$fts->prefixMatches('pl.search_vector');

        // The raw payload must not appear in the SQL text at all —
        // only inside the bound parameters.
        expect($sql)->not->toContain($malicious)
            ->and($sql)->not->toContain('DROP TABLE')
            ->and($sql)->not->toContain('--')
            ->and($fts->bindings())->toContain($malicious)
            ->and($fts->prefixBindings())->toContain($malicious);
    });

    it('rejects column expressions outside the identifier whitelist', function (): void {
        $fts = new FtsQueryBuilder('english', 'x');

        expect(fn () => $fts->matches("pl.search_vector'); DROP TABLE x; --"))
            ->toThrow(\InvalidArgumentException::class)
            ->and(fn () => $fts->rankExpression('pl.search_vector OR 1=1'))
            ->toThrow(\InvalidArgumentException::class)
            ->and(fn () => $fts->headlineExpression('(select pg_sleep(10))'))
            ->toThrow(\InvalidArgumentException::class)
            ->and(fn () => $fts->rankExpression('coalesce(pl.search_vector, (select 1))'))
            ->toThrow(\InvalidArgumentException::class)
            // Legitimate plain paths and coalesce calls stay allowed.
            ->and($fts->matches('coalesce(pl.search_vector, pe.search_vector)'))
            ->toBe('coalesce(pl.search_vector, pe.search_vector) @@ plainto_tsquery(?, ?)')
            ->and($fts->matches('pl.search_vector'))
            ->toBe('pl.search_vector @@ plainto_tsquery(?, ?)');
    });
});
