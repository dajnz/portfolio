<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * A suggestion of the unified blog-tag autocomplete
 * (`/api/blog-tags/search` — the `UnifiedTagSearchItem` wire shape):
 * an article tag (`type: "tag"`) or a tech skill (`type: "skill"`),
 * discriminated so the filter URL never mixes the independent id
 * spaces (routes.md:198-205). Immutable.
 */
final readonly class UnifiedTagOptionDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
    ) {
    }
}
