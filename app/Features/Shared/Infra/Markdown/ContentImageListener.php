<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;

/**
 * Adds `loading="lazy"` to every Markdown image of the rendered document
 * (below-fold content images defer). Body images carry no width/height metadata
 * (the editor drops the drag-resize width in the Markdown round trip), so only
 * the lazy hint is applied here — the dimension-less CLS caveat is covered by
 * the `.article-content__body img` CSS (`max-width: 100%`).
 *
 * Registered via
 * `Environment::addEventListener(DocumentParsedEvent::class, ...)`
 * (commonmark 2.6+ dropped the listener interfaces) — runs after parsing,
 * before rendering.
 */
final class ContentImageListener
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($event->getDocument()->iterator() as $node) {
            if (! $node instanceof Image) {
                continue;
            }

            $attributes = $node->data->get('attributes') ?? [];
            $attributes['loading'] = 'lazy';
            $node->data->set('attributes', $attributes);
        }
    }
}
