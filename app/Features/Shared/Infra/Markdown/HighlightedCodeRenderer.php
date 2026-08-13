<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use App\Features\Shared\Infra\Services\CodeHighlighter;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Renders the code blocks of the content pipeline: every fenced or
 * indented code block goes through the {@see CodeHighlighter} — the
 * `data-code-block` markers + the server-highlighted tokens — so the
 * public article HTML is fully functional without JavaScript.
 */
final class HighlightedCodeRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly CodeHighlighter $highlighter,
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if ($node instanceof FencedCode) {
            return $this->highlighter->highlight($node->getLiteral(), $node->getInfo() ?? '');
        }

        if ($node instanceof IndentedCode) {
            return $this->highlighter->highlight($node->getLiteral(), '');
        }

        throw new \InvalidArgumentException('HighlightedCodeRenderer can only render code blocks.');
    }
}
