<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

/**
 * The article lead-image reference — the RustFS path plus the
 * per-locale alt labels (the `lead_image_alt` jsonb column).
 * Immutable.
 */
final readonly class ArticleImageDto
{
    /**
     * @param  array<string, string|null>  $altLabels  Locale => alt text.
     */
    public function __construct(
        public string $url,
        public array $altLabels = [],
    ) {
    }
}
