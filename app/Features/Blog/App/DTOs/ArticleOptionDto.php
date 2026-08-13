<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * A published article as a picker option — `{id, slug, title}` (the
 * Related Articles multi-select of the form, B188-style). Immutable.
 */
final readonly class ArticleOptionDto
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
    ) {
    }
}
