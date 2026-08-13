<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Renders headings with the `id` assigned by {@see HeadingIdListener}
 * (replacing the default heading renderer — the anchor ids drive the
 * TableOfContents deep links).
 */
final class HeadingIdRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (! $node instanceof Heading) {
            throw new \InvalidArgumentException('HeadingIdRenderer can only render Heading nodes.');
        }

        $id = $node->data->get('heading_id');

        $attributes = $id === null
            ? ''
            : ' id="'.htmlspecialchars((string) $id, ENT_QUOTES | ENT_HTML5, 'UTF-8').'"';

        return '<h'.$node->getLevel().$attributes.'>'.$childRenderer->renderNodes($node->children()).'</h'.$node->getLevel().'>';
    }
}
