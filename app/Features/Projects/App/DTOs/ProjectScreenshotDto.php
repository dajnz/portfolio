<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * `sortOrder` is present on read (the gallery position) and null on
 * form submissions (the position is derived from the row order).
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
