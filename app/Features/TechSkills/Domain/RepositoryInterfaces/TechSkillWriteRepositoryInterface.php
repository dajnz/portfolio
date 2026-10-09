<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Domain\RepositoryInterfaces;

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;

/**
 * Write side of the tech skills domain: admin CRUD + reordering over the
 * shared Eloquent models (`SkillModel`, `SkillGroupModel`,
 * `SkillGroupTranslationModel`). Reads are owned by `Shared`'s
 * `TechSkillReadRepositoryInterface`; the entities are the shared domain
 * ones.
 */
interface TechSkillWriteRepositoryInterface
{
    /**
     * All groups with their skills and translations, sorted by group
     * `sort_order` then skill `sort_order`.
     *
     * @return TechSkillGroup[]
     */
    public function findAllGroups(): array;

    /**
     * A single group with its per-locale names (skills not loaded) — used
     * to build the group context object for new/updated skills.
     */
    public function findGroupById(int $id): ?TechSkillGroup;

    /**
     * Case-insensitive existence probe for a skill name: the DB
     * `skills.name` unique constraint is case-sensitive, so the check
     * lives here. When `$ignoreId` is set, that row is excluded (the skill
     * being updated).
     */
    public function skillNameExists(string $name, ?int $ignoreId = null): bool;

    public function saveGroup(TechSkillGroup $group): void;

    public function deleteGroup(int $id): void;

    /**
     * Applies the zero-indexed positions of the `{ "ids": [...] }`
     * payload — group IDs in the new drag order.
     *
     * @param  int[]  $ids
     */
    public function reorderGroups(array $ids): void;

    public function saveSkill(TechSkill $skill): void;

    public function deleteSkill(int $id): void;

    /**
     * Applies the skills reorder payload `{ "skills": [{ "id",
     * "skill_group_id" }] }` (drag order): each skill's array position
     * becomes `sort_order` and the submitted `skill_group_id` becomes its
     * new group (cross-group moves included).
     *
     * @param  array<int, array{id: int, skill_group_id: int}>  $skills
     */
    public function reorderSkills(array $skills): void;
}
