<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

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
