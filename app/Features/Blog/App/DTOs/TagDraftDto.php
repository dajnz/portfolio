<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * The tag data as submitted by the store/update forms and the inline
 * API creation: the raw slug value (re-resolved by the use case) and
 * the per-locale names. Immutable.
 */
final readonly class TagDraftDto
{
    /**
     * @param  array<string, string|null>  $names  Locale => name.
     */
    public function __construct(
        public string $submittedSlug,
        public array $names,
    ) {
    }

    /**
     * The EN name — the slug regeneration source (the tag slug is
     * auto-generated from the EN name).
     */
    public function enName(): string
    {
        $name = $this->names['en'] ?? '';

        return is_string($name) ? $name : '';
    }
}
