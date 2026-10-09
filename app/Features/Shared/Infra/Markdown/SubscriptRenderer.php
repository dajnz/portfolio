<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Xml\XmlNodeRendererInterface;

/**
 * Renders {@see Subscript} as `<sub>` (ADR-0024).
 */
final class SubscriptRenderer implements NodeRendererInterface, XmlNodeRendererInterface
{
    /**
     * @param  Subscript  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Subscript::assertInstanceOf($node);

        return new HtmlElement('sub', $node->data->get('attributes'), $childRenderer->renderNodes($node->children()));
    }

    public function getXmlTagName(Node $node): string
    {
        return 'subscript';
    }

    public function getXmlAttributes(Node $node): array
    {
        return [];
    }
}
