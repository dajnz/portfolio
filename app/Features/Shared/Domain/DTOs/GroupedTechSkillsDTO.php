<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\DTOs;

/**
 * Read model of a skill group with its skills.
 *
 * @property-read TechSkillGroupDTO $group
 * @property-read TechSkillDTO[] $skills Ordered by group + skill sort order.
 */
final readonly class GroupedTechSkillsDTO
{
    /**
     * @param  TechSkillDTO[]  $skills
     */
    public function __construct(
        public TechSkillGroupDTO $group,
        public array $skills,
    ) {
    }
}
