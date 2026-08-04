<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\DTOs;

/**
 * Read model of a tech skill with its group.
 *
 * @property-read int $id
 * @property-read string $name Skill name (not translatable).
 * @property-read string|null $icon Iconify identifier (e.g. `heroicons:code-bracket`).
 * @property-read int $proficiency Level on a 1-5 scale.
 * @property-read string $proficiencyLabel Translated proficiency label.
 * @property-read int $sortOrder
 * @property-read TechSkillGroupDTO $group
 */
final readonly class TechSkillDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $icon,
        public int $proficiency,
        public string $proficiencyLabel,
        public int $sortOrder,
        public TechSkillGroupDTO $group,
    ) {
    }
}
