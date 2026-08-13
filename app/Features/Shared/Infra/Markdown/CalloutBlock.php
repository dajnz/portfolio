<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * The custom callout node of the content pipeline
 * (content-management.md:483-499): a blockquote whose first line
 * carries the `> [!TYPE] Title` marker. The type is one of
 * `warning|tip|important|tradeoff` and the title is the inline
 * markdown after the marker; the remaining lines become the callout
 * body (rendered as ordinary children).
 *
 * Rendered as `<aside data-callout-type="{type}">` — the marker the
 * public `CalloutBlock` component mounts on (public-section-components.md:
 * 1286).
 */
final class CalloutBlock extends AbstractBlock
{
    public function __construct(
        private readonly string $type,
        private readonly string $title,
    ) {
    }

    public function toType(): string
    {
        return $this->type;
    }

    public function toTitle(): string
    {
        return $this->title;
    }
}
