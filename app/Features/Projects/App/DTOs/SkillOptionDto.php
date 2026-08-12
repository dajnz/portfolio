<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * A skill option of the admin pickers and the public tag filter —
 * the same `{id, name, icon}` data serves the form's tech tags and
 * the index's skill tags. Immutable.
 */
final readonly class SkillOptionDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $icon = null,
    ) {
    }
}
