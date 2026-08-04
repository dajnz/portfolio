<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Shared\Domain\DTOs\GroupedTechSkillsDTO;
use App\Features\Shared\Domain\DTOs\TechSkillDTO;
use App\Features\Shared\Domain\DTOs\TechSkillGroupDTO;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;
use App\Features\Shared\Infra\Models\SkillModel;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed read model for tech skills.
 *
 * Each method runs a single join query (skills × skill_groups ×
 * skill_group_translations) and hydrates results into the DTOs inline.
 * Group names resolve per locale with an `en` fallback; proficiency labels
 * are resolved via `__("skills.proficiency.{level}")`.
 */
final class EloquentTechSkillReadRepository implements TechSkillReadRepositoryInterface
{
    public function findAll(string $locale): array
    {
        return $this->baseQuery($locale)
            ->get()
            ->map(fn (SkillModel $row): TechSkillDTO => $this->hydrate($row))
            ->all();
    }

    public function findByIds(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->baseQuery($locale)
            ->whereIn('skills.id', $ids)
            ->get()
            ->map(fn (SkillModel $row): TechSkillDTO => $this->hydrate($row))
            ->all();
    }

    public function findAllGrouped(string $locale): array
    {
        $grouped = [];

        foreach ($this->findAll($locale) as $skill) {
            $groupId = $skill->group->id;

            if (! isset($grouped[$groupId])) {
                $grouped[$groupId] = ['group' => $skill->group, 'skills' => []];
            }

            $grouped[$groupId]['skills'][] = $skill;
        }

        return array_values(array_map(
            static fn (array $entry): GroupedTechSkillsDTO => new GroupedTechSkillsDTO($entry['group'], $entry['skills']),
            $grouped,
        ));
    }

    private function baseQuery(string $locale): EloquentBuilder
    {
        return SkillModel::query()
            ->select([
                'skills.id',
                'skills.name',
                'skills.icon as skill_icon',
                'skills.proficiency',
                'skills.sort_order as skill_sort_order',
                'skill_groups.id as group_id',
                'skill_groups.icon as group_icon',
                'skill_groups.sort_order as group_sort_order',
                DB::raw("coalesce(group_translations_locale.name, group_translations_en.name, '') as group_name"),
            ])
            ->join('skill_groups', 'skill_groups.id', '=', 'skills.skill_group_id')
            ->leftJoin('skill_group_translations as group_translations_locale', function (JoinClause $join) use ($locale): void {
                $join->on('group_translations_locale.skill_group_id', '=', 'skill_groups.id')
                    ->where('group_translations_locale.locale', '=', $locale);
            })
            ->leftJoin('skill_group_translations as group_translations_en', function (JoinClause $join): void {
                $join->on('group_translations_en.skill_group_id', '=', 'skill_groups.id')
                    ->where('group_translations_en.locale', '=', 'en');
            })
            ->orderBy('skill_groups.sort_order')
            ->orderBy('skills.sort_order')
            ->orderBy('skills.id');
    }

    private function hydrate(SkillModel $row): TechSkillDTO
    {
        $proficiency = (int) $row->proficiency;

        return new TechSkillDTO(
            id: (int) $row->id,
            name: $row->name,
            icon: $row->skill_icon,
            proficiency: $proficiency,
            proficiencyLabel: __('skills.proficiency.'.$proficiency),
            sortOrder: (int) $row->skill_sort_order,
            group: new TechSkillGroupDTO(
                id: (int) $row->group_id,
                name: $row->group_name,
                icon: $row->group_icon,
                sortOrder: (int) $row->group_sort_order,
            ),
        );
    }
}
