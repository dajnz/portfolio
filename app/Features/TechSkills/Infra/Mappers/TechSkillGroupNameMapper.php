<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Mappers;

use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;

/**
 * Converts between `skill_group_translations` rows and
 * {@see TechSkillGroupName} value objects.
 */
final class TechSkillGroupNameMapper
{
    public function toEntity(SkillGroupTranslationModel $model): TechSkillGroupName
    {
        return new TechSkillGroupName(
            locale: $model->locale,
            name: $model->name,
        );
    }

    /**
     * @return array{skill_group_id: int, locale: string, name: string}
     */
    public function toRow(TechSkillGroupName $name, int $groupId): array
    {
        return [
            'skill_group_id' => $groupId,
            'locale' => $name->toLocale(),
            'name' => $name->toName(),
        ];
    }
}
