<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\DTOs;

/**
 * Read model of a tech skill group.
 *
 * @property-read int $id
 * @property-read string $name Translated group name (per-locale).
 * @property-read string|null $icon Iconify identifier (e.g. `heroicons:folder`).
 * @property-read int $sortOrder
 */
final readonly class TechSkillGroupDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $icon,
        public int $sortOrder,
    ) {
    }
}
