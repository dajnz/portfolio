<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\ProficiencyLevel;
use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;
use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;
use App\Features\Shared\Infra\Models\SkillModel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed read model for tech skills.
 *
 * Two queries per method: one for group translations and one for
 * skills. Entities are hydrated with the pre-loaded group data so that
 * every {@see TechSkill} carries its group context
 * ({@see SkillGroupVo}) and every {@see TechSkillGroup} carries its
 * per-locale names. Value objects are created here from raw rows.
 */
final class EloquentTechSkillReadRepository implements TechSkillReadRepositoryInterface
{
    public function findAll(string $locale): array
    {
        $groups = $this->loadGroupRows();

        return $this->skillsBaseQuery()
            ->get()
            ->map(fn (SkillModel $row): TechSkill => $this->hydrateSkill($row, $groups))
            ->all();
    }

    public function findByIds(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        $skillRows = $this->skillsBaseQuery()
            ->whereIn('skills.id', $ids)
            ->get();

        $groupIds = $skillRows->pluck('skill_group_id')->unique()->all();

        $groups = $this->loadGroupRows($groupIds);

        return $skillRows
            ->map(fn (SkillModel $row): TechSkill => $this->hydrateSkill($row, $groups))
            ->all();
    }

    public function findAllGrouped(string $locale): array
    {
        $groupRows = $this->loadGroupRows();

        $skillsByGroupId = [];
        foreach ($this->skillsBaseQuery()->get() as $skillRow) {
            $groupId = (int) $skillRow->skill_group_id;
            $skillsByGroupId[$groupId][] = $this->hydrateSkill($skillRow, $groupRows);
        }

        $result = [];
        foreach ($groupRows as $groupRow) {
            $result[] = new TechSkillGroup(
                id: $groupRow['id'],
                icon: $groupRow['icon'] === null ? null : new SkillGroupIcon($groupRow['icon']),
                sortOrder: new SortOrder($groupRow['sort_order']),
                groupNames: $groupRow['translations'],
                skills: $skillsByGroupId[$groupRow['id']] ?? [],
            );
        }

        return $result;
    }

    /**
     * @param  int[]|null  $groupIds  When provided, only loads rows for these group IDs.
     * @return array<int, array{id: int, icon: string|null, sort_order: int, translations: TechSkillGroupName[]}>
     */
    private function loadGroupRows(?array $groupIds = null): array
    {
        $groupQuery = DB::table('skill_groups')
            ->select(['skill_groups.id', 'skill_groups.icon', 'skill_groups.sort_order'])
            ->orderBy('skill_groups.sort_order');

        if ($groupIds !== null) {
            $groupQuery->whereIn('skill_groups.id', $groupIds);
        }

        $rows = $groupQuery->get();

        $translationRows = DB::table('skill_group_translations')
            ->whereIn('skill_group_id', $rows->pluck('id')->all())
            ->orderBy('locale')
            ->get();

        $translationsByGroupId = [];
        foreach ($translationRows as $row) {
            $translationsByGroupId[(int) $row->skill_group_id][] = new TechSkillGroupName(
                locale: $row->locale,
                name: $row->name,
            );
        }

        $groups = [];
        foreach ($rows as $row) {
            $groups[(int) $row->id] = [
                'id' => (int) $row->id,
                'icon' => $row->icon,
                'sort_order' => (int) $row->sort_order,
                'translations' => $translationsByGroupId[(int) $row->id] ?? [],
            ];
        }

        return $groups;
    }

    /**
     * Base query that joins skill_groups for ordering only (no translation join).
     *
     * @return EloquentBuilder<SkillModel>
     */
    private function skillsBaseQuery(): EloquentBuilder
    {
        return SkillModel::query()
            ->join('skill_groups', 'skill_groups.id', '=', 'skills.skill_group_id')
            ->orderBy('skill_groups.sort_order')
            ->orderBy('skills.sort_order')
            ->orderBy('skills.id')
            ->select('skills.*');
    }

    /**
     * @param  array<int, array{id: int, icon: string|null, sort_order: int, translations: TechSkillGroupName[]}>  $groups
     */
    private function hydrateSkill(SkillModel $row, array $groups): TechSkill
    {
        $groupId = (int) $row->skill_group_id;
        $groupData = $groups[$groupId] ?? null;

        return new TechSkill(
            id: (int) $row->id,
            name: $row->name,
            icon: $row->icon === null ? null : new SkillIcon($row->icon),
            proficiency: new ProficiencyLevel((int) $row->proficiency),
            includeInResume: $row->include_in_resume,
            sortOrder: new SortOrder((int) $row->sort_order),
            group: new SkillGroupVo(
                id: $groupId,
                groupNames: $groupData['translations'] ?? [],
            ),
        );
    }
}
