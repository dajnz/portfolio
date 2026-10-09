<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\MarkdownContentPipelineInterface;
use App\Features\Shared\Infra\Markdown\CalloutBlock;
use App\Features\Shared\Infra\Markdown\CalloutBlockRenderer;
use App\Features\Shared\Infra\Markdown\CalloutBlockStartParser;
use App\Features\Shared\Infra\Markdown\ContentImageListener;
use App\Features\Shared\Infra\Markdown\HeadingIdListener;
use App\Features\Shared\Infra\Markdown\HeadingIdRenderer;
use App\Features\Shared\Infra\Markdown\HighlightedCodeRenderer;
use App\Features\Shared\Infra\Markdown\Subscript;
use App\Features\Shared\Infra\Markdown\SubscriptInlineParser;
use App\Features\Shared\Infra\Markdown\SubscriptRenderer;
use App\Features\Shared\Infra\Markdown\Superscript;
use App\Features\Shared\Infra\Markdown\SuperscriptInlineParser;
use App\Features\Shared\Infra\Markdown\SuperscriptRenderer;
use App\Features\Shared\Infra\Markdown\Underline;
use App\Features\Shared\Infra\Markdown\UnderlineDelimiterProcessor;
use App\Features\Shared\Infra\Markdown\UnderlineRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\Strikethrough\Strikethrough;
use League\CommonMark\Extension\Strikethrough\StrikethroughDelimiterProcessor;
use League\CommonMark\Extension\Strikethrough\StrikethroughRenderer;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Util\HtmlFilter;
use Illuminate\Support\Facades\Cache;

/**
 * The shared content pipeline (ADR-0008, content-management.md:466-481):
 * Markdown → safe HTML for every admin-authored body. CommonMark runs
 * in safe mode (`HtmlInput::ESCAPE` + `allow_unsafe_links: false` —
 * raw HTML is escaped, so an article can never inject `<script>` or
 * `<iframe>`), the `> [!TYPE]` callout syntax becomes the static
 * `data-callout-type` aside, code blocks are highlighted server-side
 * ({@see CodeHighlighter}) with the `data-code-block` markers, and
 * headings carry the anchor ids the TableOfContents scans for.
 *
 * The article variant is cached in Redis under
 * `article:html:{id}:{locale}:{content-hash}` (ADR-0008 — the content
 * hash rotates the key on every content change; the 8.2
 * `updated_at`-stamp scheme collided for saves within the same
 * second). Reused by any feature rendering Markdown content (Blog
 * detail, Projects detail, ...).
 *
 * Implements the app-layer {@see MarkdownContentPipelineInterface} —
 * the app layer never imports this concrete.
 */
final class MarkdownContentPipeline implements MarkdownContentPipelineInterface
{
    private const CACHE_TTL_SECONDS = 604800;

    private readonly MarkdownConverter $converter;

    public function __construct(
        private readonly CodeHighlighter $highlighter,
    ) {
        $environment = new Environment([
            'html_input' => HtmlFilter::ESCAPE,
            'allow_unsafe_links' => false,
            'max_nesting_level' => 100,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        // The editor's markdown surface extensions (ADR-0024 — the admin
        // editor buttons serialize to these syntaxes; without the
        // matching parsers they would render literally or escape to
        // visible tags):
        // - GFM pipe tables (the editor's table button).
        // - `~~strikethrough~~` (the editor's strikethrough button).
        // - `++underline++` (the editor's underline button).
        // - `<sub>…</sub>` / `<sup>…</sup>` (the editor's subscript /
        //   superscript buttons — claimed as structured nodes BEFORE
        //   the core HtmlInline parser, which html_input: ESCAPE would
        //   escape to literal text).
        $environment->addExtension(new TableExtension());
        $environment->addDelimiterProcessor(new StrikethroughDelimiterProcessor());
        $environment->addRenderer(Strikethrough::class, new StrikethroughRenderer());
        $environment->addDelimiterProcessor(new UnderlineDelimiterProcessor());
        $environment->addRenderer(Underline::class, new UnderlineRenderer());
        // Registered above the core HtmlInlineParser (priority 40) so
        // the tag pairs become structured nodes instead of escaped
        // HtmlInline output.
        $environment->addInlineParser(new SubscriptInlineParser(), 80);
        $environment->addRenderer(Subscript::class, new SubscriptRenderer());
        $environment->addInlineParser(new SuperscriptInlineParser(), 80);
        $environment->addRenderer(Superscript::class, new SuperscriptRenderer());
        // Above the core BlockQuoteStartParser (priority 70) so the
        // `> [!TYPE]` lines are claimed before the plain quote.
        $environment->addBlockStartParser(new CalloutBlockStartParser(), 80);
        $environment->addRenderer(CalloutBlock::class, new CalloutBlockRenderer($environment));
        $environment->addRenderer(FencedCode::class, new HighlightedCodeRenderer($this->highlighter));
        $environment->addRenderer(IndentedCode::class, new HighlightedCodeRenderer($this->highlighter));
        $environment->addRenderer(Heading::class, new HeadingIdRenderer());
        $environment->addEventListener(DocumentParsedEvent::class, new HeadingIdListener());
        // Marks every Markdown image `loading="lazy"` (below-fold body
        // images defer; the renderer merges node `attributes` data).
        $environment->addEventListener(DocumentParsedEvent::class, new ContentImageListener());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * The rendered article body, cached under
     * `article:html:{id}:{locale}:{content-hash}` (ADR-0008) — the key
     * embeds a hash of the markdown, so ANY content change rotates
     * the key: self-invalidating by content. The 8.2 stamp scheme
     * (`updated_at` seconds) collided when two saves landed within
     * the same second — the second save kept the old key and the
     * cached render of the first save (observed live 2026-08-16:
     * edited content never appeared on the public page).
     */
    public function articleHtml(int $id, string $locale, string $markdown): string
    {
        $key = 'article:html:'.$id.':'.$locale.':'.substr(hash('xxh128', $markdown), 0, 16);

        return Cache::remember($key, self::CACHE_TTL_SECONDS, fn (): string => $this->render($markdown));
    }

    /**
     * Renders Markdown to safe HTML (uncached).
     */
    public function render(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
