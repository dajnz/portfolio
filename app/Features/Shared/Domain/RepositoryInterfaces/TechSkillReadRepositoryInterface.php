<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

use App\Features\Shared\Domain\DTOs\GroupedTechSkillsDTO;
use App\Features\Shared\Domain\DTOs\TechSkillDTO;

/**
 * Read-only access to tech skills for all features.
 *
 * All methods return results sorted by `skill_groups.sort_order`, then
 * `skills.sort_order`. Skill group names are resolved per locale with a
 * fallback to `en` when the requested locale's translation is missing.
 */
interface TechSkillReadRepositoryInterface
{
    /**
     * @return TechSkillDTO[]
     */
    public function findAll(string $locale): array;

    /**
     * @param  int[]  $ids  Skill IDs (skills.id)
     * @return TechSkillDTO[]
     */
    public function findByIds(array $ids, string $locale): array;

    /**
     * @return GroupedTechSkillsDTO[]
     */
    public function findAllGrouped(string $locale): array;
}
