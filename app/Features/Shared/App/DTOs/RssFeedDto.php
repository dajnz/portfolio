<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

/**
 * The RSS channel data (seo.md:233-238): the localized channel
 * title/description, the site link and the 20 most recent published
 * articles. Immutable.
 */
final readonly class RssFeedDto
{
    /**
     * @param  RssItemDto[]  $items
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $link,
        public array $items,
    ) {
    }
}
