<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Delimiter\DelimiterInterface;
use League\CommonMark\Delimiter\Processor\DelimiterProcessorInterface;
use League\CommonMark\Node\Inline\AbstractStringContainer;

/**
 * The `++text++` underline delimiter processor of the content pipeline
 * (B231) — the same scheme as the strike processor, with the `+`
 * delimiter and a minimum run of 2. Runs of other lengths stay
 * literal (no pairing), so "C++" and friends are never consumed.
 */
final class UnderlineDelimiterProcessor implements DelimiterProcessorInterface
{
    public function getOpeningCharacter(): string
    {
        return '+';
    }

    public function getClosingCharacter(): string
    {
        return '+';
    }

    public function getMinLength(): int
    {
        return 2;
    }

    public function getDelimiterUse(DelimiterInterface $opener, DelimiterInterface $closer): int
    {
        if ($opener->getLength() !== 2 || $closer->getLength() !== 2) {
            return 0;
        }

        return 2;
    }

    public function process(AbstractStringContainer $opener, AbstractStringContainer $closer, int $delimiterUse): void
    {
        $underline = new Underline(str_repeat('+', $delimiterUse));

        $tmp = $opener->next();
        while ($tmp !== null && $tmp !== $closer) {
            $next = $tmp->next();
            $underline->appendChild($tmp);
            $tmp = $next;
        }

        $opener->insertAfter($underline);
    }
}
