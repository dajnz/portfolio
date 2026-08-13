<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

/**
 * The continuation parser of the {@see CalloutBlock}: a container
 * whose body lines follow the blockquote convention — every line
 * prefixed with `>` (the marker line was already consumed by the
 * start parser). A line without the prefix closes the callout.
 */
final class CalloutBlockParser extends AbstractBlockContinueParser
{
    private readonly CalloutBlock $block;

    public function __construct(string $type, string $title)
    {
        $this->block = new CalloutBlock($type, $title);
    }

    public function getBlock(): CalloutBlock
    {
        return $this->block;
    }

    public function isContainer(): bool
    {
        return true;
    }

    public function canContain(AbstractBlock $childBlock): bool
    {
        return true;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if (! $cursor->isIndented() && $cursor->getNextNonSpaceCharacter() === '>') {
            $cursor->advanceToNextNonSpaceOrTab();
            $cursor->advanceBy(1);
            $cursor->advanceBySpaceOrTab();

            return BlockContinue::at($cursor);
        }

        return BlockContinue::none();
    }
}
