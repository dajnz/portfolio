<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * An image reference of a project (content-management.md:572-586):
 * the RustFS path plus the per-locale alt labels and the optional
 * per-locale descriptions — the shared shape of the preview picture
 * and the screenshots (which add their own titles). Immutable.
 */
final readonly class ProjectImageDto
{
    /**
     * @param  array<string, string|null>  $altLabels  Locale => alt text.
     * @param  array<string, string|null>  $descriptions  Locale => description (may be empty).
     */
    public function __construct(
        public ?string $url,
        public array $altLabels = [],
        public array $descriptions = [],
    ) {
    }
}
