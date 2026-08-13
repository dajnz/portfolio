<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use App\Features\Shared\Domain\ValueObjects\Slug;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\StringContainerHelper;

/**
 * Assigns a stable `id` attribute to every heading of the rendered
 * document (the TableOfContents component scans for `<h2 id>`
 * / `<h3 id>` — public-section-components.md:1314). The id is the
 * slugified heading text (the shared {@see Slug} generation — Cyrillic
 * transliterated, so Russian headings produce usable anchors);
 * duplicates within one document get a numeric suffix.
 *
 * Registered as a plain callable via
 * `Environment::addEventListener(DocumentParsedEvent::class, ...)`
 * (commonmark 2.6+ dropped the listener interfaces).
 */
final class HeadingIdListener
{
    public function __invoke(DocumentParsedEvent $event): void
    {
        $used = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if (! $node instanceof Heading) {
                continue;
            }

            $base = Slug::generate(StringContainerHelper::getChildText($node));
            $id = $base;
            $counter = 2;

            while (isset($used[$id])) {
                $id = $base.'-'.$counter++;
            }

            $used[$id] = true;
            $node->data->set('heading_id', $id);
        }
    }
}
