<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\MarkdownContentPipelineInterface;
use App\Features\Shared\Infra\Markdown\CalloutBlock;
use App\Features\Shared\Infra\Markdown\CalloutBlockRenderer;
use App\Features\Shared\Infra\Markdown\CalloutBlockStartParser;
use App\Features\Shared\Infra\Markdown\HeadingIdListener;
use App\Features\Shared\Infra\Markdown\HeadingIdRenderer;
use App\Features\Shared\Infra\Markdown\HighlightedCodeRenderer;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Util\HtmlFilter;

/**
 * The shared content pipeline (B23, content-management.md:466-481):
 * Markdown → safe HTML for every admin-authored body. CommonMark runs
 * in safe mode (`HtmlInput::ESCAPE` + `allow_unsafe_links: false` —
 * raw HTML is escaped, so an article can never inject `<script>` or
 * `<iframe>`), the `> [!TYPE]` callout syntax becomes the static
 * `data-callout-type` aside, code blocks are highlighted server-side
 * ({@see CodeHighlighter}) with the `data-code-block` markers, and
 * headings carry the anchor ids the TableOfContents scans for.
 *
 * The article variant is cached in Redis under
 * `article:html:{id}:{locale}:{updated_at}` (content-management.md:
 * 479) — the `updated_at` stamp makes the entry self-invalidating on
 * every content save. Reused by any feature rendering Markdown
 * content (Blog detail, Projects detail, ...).
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
        // Above the core BlockQuoteStartParser (priority 70) so the
        // `> [!TYPE]` lines are claimed before the plain quote.
        $environment->addBlockStartParser(new CalloutBlockStartParser(), 80);
        $environment->addRenderer(CalloutBlock::class, new CalloutBlockRenderer($environment));
        $environment->addRenderer(FencedCode::class, new HighlightedCodeRenderer($this->highlighter));
        $environment->addRenderer(IndentedCode::class, new HighlightedCodeRenderer($this->highlighter));
        $environment->addRenderer(Heading::class, new HeadingIdRenderer());
        $environment->addEventListener(DocumentParsedEvent::class, new HeadingIdListener());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * The rendered article body, cached under
     * `article:html:{id}:{locale}:{updated_at}` — the timestamp makes
     * the key rotate on every save of the base row, so stale HTML can
     * never be served.
     */
    public function articleHtml(int $id, string $locale, ?DateTimeImmutable $updatedAt, string $markdown): string
    {
        $key = 'article:html:'.$id.':'.$locale.':'.($updatedAt?->getTimestamp() ?? 0);

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
