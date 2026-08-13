<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\InlineParserEngine;
use League\CommonMark\Reference\ReferenceMap;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Renders the {@see CalloutBlock} as the static callout markup the
 * public `CalloutBlock` component enhances
 * (public-section-components.md:1286, mockup `blog-article.html`):
 *
 *     <aside class="callout callout--{type}"
 *            data-callout-type="{type}">
 *         <div class="callout__title">{title}</div>
 *         <div class="callout__content">{body}</div>
 *     </aside>
 *
 * The title is parsed as inline markdown (a fresh paragraph parsed via
 * the inline engine, its children rendered and discarded — the
 * callout node's own children are the body).
 */
final class CalloutBlockRenderer implements NodeRendererInterface
{
    public function __construct(
        private readonly EnvironmentInterface $environment,
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (! $node instanceof CalloutBlock) {
            throw new \InvalidArgumentException('CalloutBlockRenderer can only render CalloutBlock nodes.');
        }

        $titleHtml = $node->toTitle() === ''
            ? ''
            : '<div class="callout__title">'.$this->renderTitle($node, $childRenderer).'</div>';

        return '<aside class="callout callout--'.$node->toType().'" data-callout-type="'.$node->toType().'">'
            .$titleHtml
            .'<div class="callout__content">'.$childRenderer->renderNodes($node->children()).'</div>'
            .'</aside>';
    }

    private function renderTitle(CalloutBlock $node, ChildNodeRendererInterface $childRenderer): string
    {
        $paragraph = new Paragraph();
        (new InlineParserEngine($this->environment, new ReferenceMap()))->parse($node->toTitle(), $paragraph);

        return $childRenderer->renderNodes($paragraph->children());
    }
}
