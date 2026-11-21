<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * The `<br>` tag of the content pipeline — the admin editor's hard-break
 * serialization (Shift+Enter, including inside table cells). The parser claims
 * the tag BEFORE the core HtmlInline parser, whose `html_input: ESCAPE` output
 * would render it as literal text. The built-in {@see Newline} node keeps the
 * output on the same safe `<br />` path as Markdown hard breaks.
 */
final class HardBreakInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('<br\\s*\\/?>');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $inlineContext->getCursor()->advanceBy(\strlen($inlineContext->getFullMatch()));

        $inlineContext->getContainer()->appendChild(new Newline(Newline::HARDBREAK));

        return true;
    }
}
