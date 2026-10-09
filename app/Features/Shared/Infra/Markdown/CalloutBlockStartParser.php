<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * Starts a {@see CalloutBlock} when a block-quote line carries the
 * `> [!TYPE]` marker. The type is validated against the four supported kinds —
 * any other marker falls through to the ordinary blockquote parsing (the parser
 * returns `BlockStart::none()` and the default BlockQuoteStartParser takes
 * over).
 *
 * The parser consumes the whole first line (the cursor is advanced past the
 * marker + title), so the title never becomes a paragraph child; the body lines
 * are handled by the callout parser's blockquote-style continuation.
 */
final class CalloutBlockStartParser implements BlockStartParserInterface
{
    private const TYPES = ['warning', 'tip', 'important', 'tradeoff'];

    private const MARKER_PATTERN = '/^\s{0,3}>\s*\[!([A-Za-z]+)\](?:\s*(.+))?$/i';

    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        if ($cursor->isIndented() || $cursor->getNextNonSpaceCharacter() !== '>') {
            return BlockStart::none();
        }

        if (preg_match(self::MARKER_PATTERN, $cursor->getLine(), $matches) !== 1) {
            return BlockStart::none();
        }

        $type = mb_strtolower($matches[1]);

        if (! in_array($type, self::TYPES, true)) {
            return BlockStart::none();
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new CalloutBlockParser($type, $matches[2] ?? ''))->at($cursor);
    }
}
