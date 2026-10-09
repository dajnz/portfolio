<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Node\Inline\Text;

/**
 * The `<sup>…</sup>` inline parser of the content pipeline — the
 * {@see SubscriptInlineParser} twin for superscript.
 */
final class SuperscriptInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        // The closing `</sup>` slash is escaped — InlineParserMatch
        // wraps the pattern in `/.../` delimiters.
        return InlineParserMatch::regex('<sup>([\\s\\S]*?)<\\/sup>');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $inlineContext->getCursor()->advanceBy(\strlen($inlineContext->getFullMatch()));

        $node = new Superscript();
        $node->appendChild(new Text($inlineContext->getSubMatches()[0] ?? ''));

        $inlineContext->getContainer()->appendChild($node);

        return true;
    }
}
