<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Blog\Domain\Entities\ArticleTag;

/**
 * The tag data of the tag form: the stored tag with the per-locale
 * names map (the LocaleTabs) and the locale-resolved display name.
 * Immutable.
 */
final readonly class TagDataDto
{
    /**
     * @param  array<string, string>  $names  Locale => name.
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public array $names,
    ) {
    }

    public static function fromEntity(ArticleTag $tag, string $locale): self
    {
        return new self(
            id: $tag->toId(),
            slug: $tag->toSlug(),
            name: $tag->nameFor($locale),
            names: $tag->namesByLocale(),
        );
    }
}
