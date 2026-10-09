<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

final readonly class ProjectEngineeringDetailDto
{
    /**
     * @param  array<string, string|null>  $titles  Locale => title.
     * @param  array<string, string|null>  $descriptions  Locale => description.
     */
    public function __construct(
        public ?int $id,
        public ?string $icon,
        public array $titles = [],
        public array $descriptions = [],
    ) {
    }
}
