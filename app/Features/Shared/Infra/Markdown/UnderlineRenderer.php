<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Xml\XmlNodeRendererInterface;

/**
 * Renders the {@see Underline} node as `<u>`.
 */
final class UnderlineRenderer implements NodeRendererInterface, XmlNodeRendererInterface
{
    /**
     * @param  Underline  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Underline::assertInstanceOf($node);

        return new HtmlElement('u', $node->data->get('attributes'), $childRenderer->renderNodes($node->children()));
    }

    public function getXmlTagName(Node $node): string
    {
        return 'underline';
    }

    public function getXmlAttributes(Node $node): array
    {
        return [];
    }
}
