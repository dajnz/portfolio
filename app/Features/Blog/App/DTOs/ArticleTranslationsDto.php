<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * The set of per-locale translations of an article — the LocaleTabs
 * maps (content-management.md:420). Title, short description and
 * content are required per ACTIVE locale (validated by the request,
 * content-management.md:679-683). Immutable.
 */
final readonly class ArticleTranslationsDto
{
    /**
     * @param  array<string, string|null>  $titles  Locale => title.
     * @param  array<string, string|null>  $shortDescriptions  Locale => short description.
     * @param  array<string, string|null>  $contents  Locale => Markdown content.
     */
    public function __construct(
        public array $titles = [],
        public array $shortDescriptions = [],
        public array $contents = [],
    ) {
    }

    /**
     * The EN title — the slug regeneration source (A3, content-
     * management.md:430 — the slug is generated from the default-locale
     * title only).
     */
    public function enTitle(): string
    {
        $title = $this->titles['en'] ?? '';

        return is_string($title) ? $title : '';
    }
}
