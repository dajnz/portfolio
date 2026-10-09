<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

final readonly class ProjectLinksDto
{
    public function __construct(
        public ?string $sourceCodeUrl = null,
        public ?string $liveDemoUrl = null,
    ) {
    }
}
