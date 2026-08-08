<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Mappers;

use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;
use App\Features\Shared\Infra\Models\SkillGroupModel;

/**
 * Converts between `skill_groups` rows (with their translation rows
 * pre-loaded via the `translations` relation) and
 * {@see TechSkillGroup} entities, and back to the row data. Value
 * objects are created here from the raw columns.
 */
final class TechSkillGroupMapper
{
    public function __construct(
        private readonly TechSkillGroupNameMapper $nameMapper,
    ) {
    }

    public function toEntity(SkillGroupModel $model): TechSkillGroup
    {
        $translations = $model->relationLoaded('translations')
            ? $model->translations
            : $model->translations()->get();

        return new TechSkillGroup(
            id: $model->id,
            icon: $model->icon === null ? null : new SkillGroupIcon($model->icon),
            sortOrder: new SortOrder((int) $model->sort_order),
            groupNames: $translations
                ->map(fn (mixed $row): TechSkillGroupName => $this->nameMapper->toEntity($row))
                ->all(),
            skills: [],
        );
    }

    /**
     * @return array{icon: string|null, sort_order: int}
     */
    public function toRow(TechSkillGroup $group): array
    {
        return [
            'icon' => $group->toIcon(),
            'sort_order' => $group->toSortOrder(),
        ];
    }
}
