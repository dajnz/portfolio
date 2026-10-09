<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Mappers;

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\ValueObjects\ProficiencyLevel;
use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Infra\Models\SkillModel;

/**
 * Converts between `skills` rows and {@see TechSkill} entities. The group
 * context ({@see SkillGroupVo}) is supplied by the caller, since group
 * rows are loaded separately.
 */
final class TechSkillMapper
{
    public function toEntity(SkillModel $model, SkillGroupVo $group): TechSkill
    {
        return new TechSkill(
            id: $model->id,
            name: $model->name,
            icon: $model->icon === null ? null : new SkillIcon($model->icon),
            proficiency: new ProficiencyLevel((int) $model->proficiency),
            includeInResume: $model->include_in_resume,
            sortOrder: new SortOrder((int) $model->sort_order),
            group: $group,
        );
    }

    /**
     * @return array{skill_group_id: int, name: string, icon: string|null, proficiency: int, include_in_resume: bool, sort_order: int}
     */
    public function toRow(TechSkill $skill): array
    {
        return [
            'skill_group_id' => $skill->toGroupId(),
            'name' => $skill->toName(),
            'icon' => $skill->toIcon(),
            'proficiency' => $skill->toProficiency(),
            'include_in_resume' => $skill->toIncludeInResume(),
            'sort_order' => $skill->toSortOrder(),
        ];
    }
}
