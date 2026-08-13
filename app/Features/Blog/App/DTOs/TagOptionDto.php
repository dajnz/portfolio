<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * A tag as a picker/list option — the `TagData` wire shape
 * (`{id, slug, name}` with the locale-resolved name). Immutable.
 */
final readonly class TagOptionDto
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
    ) {
    }
}
