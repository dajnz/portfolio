<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\Infra\Services\CodeHighlighter;
use App\Features\Shared\Infra\Services\MarkdownContentPipeline;
use Highlight\Highlighter;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * The shared content pipeline (B23, content-management.md:466-481):
 * safe-mode CommonMark (raw HTML escaped, unsafe links blocked), the
 * `> [!TYPE]` callouts, the server-highlighted code blocks with the
 * `data-code-block` markers and the heading anchor ids — plus the
 * Redis `article:html:{id}:{locale}:{updated_at}` cache.
 */
describe('MarkdownContentPipeline', function (): void {
    it('escapes raw HTML instead of passing it through', function (): void {
        $html = pipeline()->render('Body <script>alert(1)</script> and <img src=x onerror=alert(1)>.');

        expect($html)->toContain('&lt;script&gt;')
            ->not->toContain('<script>')
            ->and($html)->toContain('&lt;img')
            ->not->toContain('<img src=x');
    });

    it('renders the B231 editor marks: strikethrough, underline, sub and sup', function (): void {
        $html = pipeline()->render(
            '~~struck~~ ++underlined++ <sub>down</sub> <sup>up</sup>'.PHP_EOL
        );

        expect($html)->toContain('<del>struck</del>')
            ->toContain('<u>underlined</u>')
            ->toContain('<sub>down</sub>')
            ->toContain('<sup>up</sup>')
            ->not->toContain('&lt;sub&gt;')
            ->not->toContain('&lt;sup&gt;');
    });

    it('escapes unterminated or malformed sub/sup tags like any other raw HTML', function (): void {
        $html = pipeline()->render('<sub onmouseover="alert(1)">x</sub> <sup>');

        expect($html)->not->toContain('<sub onmouseover')
            ->and($html)->not->toContain('<sup>');
    });

    it('renders GFM pipe tables with the header row', function (): void {
        $html = pipeline()->render(
            '| Name | Role |'.PHP_EOL
            .'| --- | --- |'.PHP_EOL
            .'| Ada | Dev |'.PHP_EOL
        );

        expect($html)->toContain('<table>')
            ->toContain('<th>Name</th>')
            ->toContain('<td>Dev</td>');
    });

    it('blocks unsafe links', function (): void {
        $html = pipeline()->render('[click](javascript:alert(1))');

        expect($html)->not->toContain('href="javascript:');
    });

    it('renders the four callout kinds with the marker attributes', function (): void {
        $html = pipeline()->render(
            '> [!WARNING] Watch out'.PHP_EOL.'> This is a warning.'.PHP_EOL.PHP_EOL
            .'> [!TIP] Tip **title**'.PHP_EOL.'> A tip body.'.PHP_EOL.PHP_EOL
            .'> [!IMPORTANT]'.PHP_EOL.'> Important body.'.PHP_EOL.PHP_EOL
            .'> [!TRADEOFF] Tradeoff'.PHP_EOL.'> A tradeoff body.'
        );

        expect($html)->toContain('<aside role="note" class="callout callout--warning" data-callout-type="warning">')
            ->and($html)->toContain('<div class="callout__title">Watch out</div>')
            ->and($html)->toContain('<div class="callout__content"><p>This is a warning.</p></div>')
            ->and($html)->toContain('data-callout-type="tip"')
            ->and($html)->toContain('<div class="callout__title">Tip <strong>title</strong></div>')
            ->and($html)->toContain('data-callout-type="important"')
            ->and($html)->toContain('<div class="callout__content"><p>Important body.</p></div>')
            ->and($html)->toContain('data-callout-type="tradeoff"');
    });

    it('keeps plain blockquotes and unknown markers as quotes', function (): void {
        $html = pipeline()->render('> Plain quote.'.PHP_EOL.PHP_EOL.'> [!UNKNOWN] Still a quote.');

        expect($html)->toContain('<blockquote>')
            ->not->toContain('data-callout-type')
            ->and(substr_count($html, '<blockquote>'))->toBe(2);
    });

    it('highlights fenced code with the marker attributes and inline colors', function (): void {
        $html = pipeline()->render('```php'.PHP_EOL.'echo "hi";'.PHP_EOL.'```');

        expect($html)->toContain('<pre data-code-block data-language="php" data-raw="echo &quot;hi&quot;;')
            ->and($html)->toContain('<span style="color:#1e7a5b">')
            ->and($html)->toContain('<code>');
    });

    it('B270: auto-detects the language of language-less fences', function (): void {
        $html = pipeline()->render('```'.PHP_EOL.'const answer = 42;'.PHP_EOL.'```');

        expect($html)->toContain('data-language="javascript"')
            ->and($html)->toContain('style="color:')
            // The raw code attribute stays the plain source (the fence
            // content keeps its trailing newline).
            ->and($html)->toContain('data-raw="const answer = 42;');
    });

    it('falls back to plain escaped text when auto-detection yields nothing', function (): void {
        // A single short line — no language scores above zero.
        $html = pipeline()->render('```'.PHP_EOL.'zzz'.PHP_EOL.'```');

        expect($html)->toContain('data-language=""')
            ->and($html)->not->toContain('style="color:')
            ->and($html)->toContain('zzz');
    });

    it('falls back to plain escaped text for unknown languages', function (): void {
        $html = pipeline()->render('```notalanguage'.PHP_EOL.'<tag> content'.PHP_EOL.'```');

        expect($html)->toContain('data-language="notalanguage"')
            ->and($html)->toContain('&lt;tag&gt; content')
            ->not->toContain('style="color:');
    });

    it('XSS-regression: highlighted code never carries raw HTML', function (): void {
        // Script tags, quote-based attribute breakout and a FAKE
        // span that mimics the highlighter's own output — the code
        // content is escaped by the engine and the post-processor
        // only rewrites literal `<span class="hljs-...">` openings
        // (escaped content can never produce one).
        $code = '<script>alert(1)</script>'
            .PHP_EOL.'<span class="hljs-keyword" onmouseover="alert(2)">fake</span>'
            .PHP_EOL.'echo "onmouseover=\'x\'";';

        $html = pipeline()->render('```php'.PHP_EOL.$code.PHP_EOL.'```');

        expect($html)->not->toContain('<script>')
            // The fake span is escaped text — no literal tag opening.
            ->and($html)->not->toContain('<span class="hljs-keyword"')
            ->and($html)->toContain('&lt;script&gt;')
            // The data-raw attribute is quote-escaped (ENT_QUOTES).
            ->and($html)->toContain('&quot;')
            // The only literal span openings carry the theme style.
            ->and(preg_match_all('/<span /', $html))->toBeGreaterThan(0)
            ->and(preg_match_all('/<span (?!style="color:)/', $html))->toBe(0);
    });

    it('assigns deduplicated heading ids for the table of contents', function (): void {
        $html = pipeline()->render('## Solution'.PHP_EOL.PHP_EOL.'## Solution'.PHP_EOL.PHP_EOL.'### Решение проблемы');

        expect($html)->toContain('<h2 id="solution">Solution</h2>')
            ->and($html)->toContain('<h2 id="solution-2">Solution</h2>')
            ->and($html)->toContain('<h3 id="reshenie-problemy">');
    });

    it('marks every Markdown image loading="lazy" (architecture.md below-fold rule)', function (): void {
        $html = pipeline()->render('![Scheme](/assets/blog/scheme.webp) text ![Second](/assets/blog/two.png)');

        expect($html)->toContain('<img loading="lazy" src="/assets/blog/scheme.webp" alt="Scheme" />')
            ->and($html)->toContain('<img loading="lazy" src="/assets/blog/two.png" alt="Second" />');
    });

    it('keys the cache by id/locale/content hash', function (): void {
        $key = 'article:html:7:en:'.substr(hash('xxh128', '# Title'), 0, 16);

        pipeline()->articleHtml(7, 'en', '# Title');

        expect(Cache::has($key))->toBeTrue()
            ->and(Cache::has('article:html:7:ru:'.substr(hash('xxh128', '# Title'), 0, 16)))->toBeFalse()
            ->and(Cache::has('article:html:7:en:'.substr(hash('xxh128', '# Other'), 0, 16)))->toBeFalse();
    });

    it('caches a rendered article once and serves the same HTML', function (): void {
        $first = pipeline()->articleHtml(1, 'en', '# Hello');
        $second = pipeline()->articleHtml(1, 'en', '# Hello');

        expect($second)->toBe($first)
            ->and($first)->toContain('<h1 id="hello">Hello</h1>');
    });

    it('B270-regression: a content change within the same second never serves the old render', function (): void {
        // The 8.2 `updated_at`-stamp key collided when two saves
        // landed in the same second — the second render hit the first
        // save's cached HTML. The content hash in the key rotates
        // regardless of the timestamp precision.
        $before = pipeline()->articleHtml(9, 'en', '## First');
        $after = pipeline()->articleHtml(9, 'en', '## Second');

        expect($before)->toContain('<h2 id="first">')
            ->and($after)->toContain('<h2 id="second">')
            ->and($after)->not->toBe($before);
    });
});

function pipeline(): MarkdownContentPipeline
{
    // The unit suite does not boot the Laravel app (tests/Pest.php
    // extends TestCase for `integration` only) — the pipeline and its
    // cache are constructed manually with an in-memory store.
    static $cache = null;
    $cache ??= Cache::swap(new Repository(new ArrayStore()));

    return new MarkdownContentPipeline(new CodeHighlighter(new Highlighter()));
}
