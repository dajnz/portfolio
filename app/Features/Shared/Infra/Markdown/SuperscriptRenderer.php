<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Xml\XmlNodeRendererInterface;

/**
 * Renders {@see Superscript} as `<sup>` (ADR-0024).
 */
final class SuperscriptRenderer implements NodeRendererInterface, XmlNodeRendererInterface
{
    /**
     * @param  Superscript  $node
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Superscript::assertInstanceOf($node);

        return new HtmlElement('sup', $node->data->get('attributes'), $childRenderer->renderNodes($node->children()));
    }

    public function getXmlTagName(Node $node): string
    {
        return 'superscript';
    }

    public function getXmlAttributes(Node $node): array
    {
        return [];
    }
}
