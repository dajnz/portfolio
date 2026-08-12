<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * A gallery row of a project (content-management.md:160): the image
 * ({@see ProjectImageDto} — path + per-locale alt labels/descriptions),
 * the per-locale short headings and the reconcile-by-id marker (A2:
 * an existing id updates the row, its absence creates a new one).
 * `sortOrder` is present on read (the gallery position) and null on
 * form submissions (the position is derived from the row order).
 * Immutable.
 */
final readonly class ProjectScreenshotDto
{
    /**
     * @param  array<string, string|null>  $titles  Locale => short heading.
     */
    public function __construct(
        public ?int $id,
        public ProjectImageDto $image,
        public array $titles = [],
        public ?int $sortOrder = null,
    ) {
    }
}
