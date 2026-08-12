<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * An engineering detail of a project (content-management.md:160): the
 * curated Iconify `icon` (config/portfolio.php →
 * `icons.sets.engineering.icons`), the per-locale title/description
 * maps and the reconcile-by-id marker (A2). Immutable.
 */
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
