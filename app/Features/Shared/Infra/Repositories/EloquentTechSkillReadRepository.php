<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Infra\Models\SkillModel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed read model for tech skills.
 *
 * Uses two queries per method: one for
 * group translations and one for skills. Entities are hydrated with the
 * pre-loaded group data so that every {@see TechSkill} carries its group
 * identity and localised name.
 */
final class EloquentTechSkillReadRepository implements TechSkillReadRepositoryInterface
{
    public function findAll(string $locale): array
    {
        $groups = $this->loadGroupRows($locale);

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

        $groups = $this->loadGroupRows($locale, $groupIds);

        return $skillRows
            ->map(fn (SkillModel $row): TechSkill => $this->hydrateSkill($row, $groups))
            ->all();
    }

    public function findAllGrouped(string $locale): array
    {
        $groupRows = $this->loadGroupRows($locale);

        $skillsByGroupId = [];
        foreach ($this->skillsBaseQuery()->get() as $skillRow) {
            $groupId = (int) $skillRow->skill_group_id;
            $skillsByGroupId[$groupId][] = $this->hydrateSkill($skillRow, $groupRows);
        }

        $result = [];
        foreach ($groupRows as $groupId => $groupData) {
            $result[] = new TechSkillGroup(
                id: $groupData['id'],
                name: $groupData['name'],
                icon: $groupData['icon'],
                sortOrder: $groupData['sort_order'],
                skills: $skillsByGroupId[$groupId] ?? [],
            );
        }

        return $result;
    }

    /**
     * @param  int[]|null  $groupIds  When provided, only loads rows for these group IDs.
     * @return array<int, array{id: int, name: string, icon: string|null, sort_order: int}>
     */
    private function loadGroupRows(string $locale, ?array $groupIds = null): array
    {
        $query = DB::table('skill_groups')
            ->select([
                'skill_groups.id',
                'skill_groups.icon',
                'skill_groups.sort_order',
                DB::raw("coalesce(gl.name, ge.name, '') as name"),
            ])
            ->leftJoin('skill_group_translations as gl', function ($join) use ($locale): void {
                $join->on('gl.skill_group_id', '=', 'skill_groups.id')
                    ->where('gl.locale', '=', $locale);
            })
            ->leftJoin('skill_group_translations as ge', function ($join): void {
                $join->on('ge.skill_group_id', '=', 'skill_groups.id')
                    ->where('ge.locale', '=', 'en');
            })
            ->orderBy('skill_groups.sort_order');

        if ($groupIds !== null) {
            $query->whereIn('skill_groups.id', $groupIds);
        }

        $rows = $query->get();

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row->id] = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'icon' => $row->icon,
                'sort_order' => (int) $row->sort_order,
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
     * @param  array<int, array{id: int, name: string, icon: string|null, sort_order: int}>  $groups
     */
    private function hydrateSkill(SkillModel $row, array $groups): TechSkill
    {
        $groupId = (int) $row->skill_group_id;

        return new TechSkill(
            id: (int) $row->id,
            name: $row->name,
            icon: $row->icon,
            proficiency: (int) $row->proficiency,
            sortOrder: (int) $row->sort_order,
            groupId: $groupId,
            groupName: $groups[$groupId]['name'] ?? '',
        );
    }
}
