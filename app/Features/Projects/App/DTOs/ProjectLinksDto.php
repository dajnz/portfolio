<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The project's URL pair (content-management.md:147) — the source-code
 * repository (not necessarily GitHub) and the live demo. Immutable.
 */
final readonly class ProjectLinksDto
{
    public function __construct(
        public ?string $sourceCodeUrl = null,
        public ?string $liveDemoUrl = null,
    ) {
    }
}
