<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use DateTimeImmutable;

/**
 * A single item of the RSS feed (seo.md:233-238): title, the
 * canonical link, the description, the publication date, the author
 * (the site name), the category names (article tags + attached
 * skills — each as a separate `<category>`) and the optional lead
 * image enclosure. Immutable.
 */
final readonly class RssItemDto
{
    /**
     * @param  string[]  $categories
     */
    public function __construct(
        public string $title,
        public string $link,
        public string $description,
        public DateTimeImmutable $publishedAt,
        public string $author,
        public array $categories,
        public ?string $enclosureUrl = null,
    ) {
    }
}
