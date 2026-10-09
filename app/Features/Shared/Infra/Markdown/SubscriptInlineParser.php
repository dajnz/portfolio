<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Node\Inline\Text;

/**
 * The `<sub>…</sub>` inline parser of the content pipeline. Registered above
 * the core HtmlInline parser (priority 10 vs 0), so the tag pair becomes a
 * structured {@see Subscript} node instead of an escaped HtmlInline. The inner
 * content is plain escaped text — no nested markdown inside the tag pair.
 */
final class SubscriptInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        // The closing `</sub>` slash is escaped — InlineParserMatch
        // wraps the pattern in `/.../` delimiters.
        return InlineParserMatch::regex('<sub>([\\s\\S]*?)<\\/sub>');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $inlineContext->getCursor()->advanceBy(\strlen($inlineContext->getFullMatch()));

        $node = new Subscript();
        $node->appendChild(new Text($inlineContext->getSubMatches()[0] ?? ''));

        $inlineContext->getContainer()->appendChild($node);

        return true;
    }
}
