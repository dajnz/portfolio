<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\Domain\DTOs\GroupedTechSkillsDTO;
use App\Features\Shared\Domain\DTOs\TechSkillDTO;
use App\Features\Shared\Domain\DTOs\TechSkillGroupDTO;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\Shared\Infra\Repositories\EloquentTechSkillReadRepository;

/**
 * @return SkillGroupModel
 */
function createSkillGroup(int $sortOrder, ?string $icon = null): SkillGroupModel
{
    return SkillGroupModel::factory()->create(['sort_order' => $sortOrder, 'icon' => $icon]);
}

/**
 * @return SkillModel
 */
function createSkill(SkillGroupModel $group, string $name, int $proficiency, int $sortOrder, ?string $icon = null): SkillModel
{
    return SkillModel::factory()->create([
        'skill_group_id' => $group->id,
        'name' => $name,
        'icon' => $icon,
        'proficiency' => $proficiency,
        'sort_order' => $sortOrder,
    ]);
}

function translateGroup(SkillGroupModel $group, string $locale, string $name): void
{
    SkillGroupTranslationModel::factory()->create([
        'skill_group_id' => $group->id,
        'locale' => $locale,
        'name' => $name,
    ]);
}

describe('EloquentTechSkillReadRepository', function (): void {
    it('returns all skills ordered by group sort_order then skill sort_order', function (): void {
        $backend = createSkillGroup(2);
        translateGroup($backend, 'en', 'Backend');
        $frontend = createSkillGroup(1);
        translateGroup($frontend, 'en', 'Frontend');

        createSkill($frontend, 'Vue.js', 4, 2);
        createSkill($backend, 'PHP', 4, 1);
        createSkill($frontend, 'TypeScript', 5, 1);
        createSkill($backend, 'Laravel', 5, 2);

        $repository = new EloquentTechSkillReadRepository();

        $skills = $repository->findAll('en');

        expect(array_map(
            static fn (TechSkillDTO $skill): string => $skill->name,
            $skills,
        ))->toBe(['TypeScript', 'Vue.js', 'PHP', 'Laravel']);
    });

    it('resolves locale group names with an en fallback', function (): void {
        $backend = createSkillGroup(1);
        translateGroup($backend, 'en', 'Backend');
        translateGroup($backend, 'ru', 'Бэкенд');
        $frontend = createSkillGroup(2);
        translateGroup($frontend, 'en', 'Frontend');

        createSkill($backend, 'Laravel', 5, 1);
        createSkill($frontend, 'Vue.js', 4, 1);

        $repository = new EloquentTechSkillReadRepository();

        $skills = $repository->findAll('ru');

        expect(array_map(
            static fn (TechSkillDTO $skill): string => $skill->group->name,
            $skills,
        ))->toBe(['Бэкенд', 'Frontend']);
    });

    it('hydrates full skill and group read models', function (): void {
        $backend = createSkillGroup(1, 'heroicons:server');
        translateGroup($backend, 'en', 'Backend');
        $skill = createSkill($backend, 'Laravel', 5, 1, 'simple-icons:laravel');

        $repository = new EloquentTechSkillReadRepository();

        /** @var TechSkillDTO $dto */
        $dto = $repository->findAll('en')[0];

        expect($dto)->toBeInstanceOf(TechSkillDTO::class)
            ->and($dto->id)->toBe($skill->id)
            ->and($dto->name)->toBe('Laravel')
            ->and($dto->icon)->toBe('simple-icons:laravel')
            ->and($dto->proficiency)->toBe(5)
            ->and($dto->sortOrder)->toBe(1)
            ->and($dto->group)->toBeInstanceOf(TechSkillGroupDTO::class)
            ->and($dto->group->id)->toBe($backend->id)
            ->and($dto->group->name)->toBe('Backend')
            ->and($dto->group->icon)->toBe('heroicons:server')
            ->and($dto->group->sortOrder)->toBe(1);
    });

    it('resolves proficiency labels via the skills.proficiency translation keys', function (): void {
        $group = createSkillGroup(1);
        translateGroup($group, 'en', 'Backend');
        createSkill($group, 'Laravel', 3, 1);

        $repository = new EloquentTechSkillReadRepository();

        $skill = $repository->findAll('en')[0];

        expect($skill->proficiencyLabel)->toBe(__('skills.proficiency.3'));
    });

    it('finds skills by their ids in sort order, not input order', function (): void {
        $group = createSkillGroup(1);
        translateGroup($group, 'en', 'Backend');

        $first = createSkill($group, 'PHP', 4, 1);
        $second = createSkill($group, 'Laravel', 5, 2);

        $repository = new EloquentTechSkillReadRepository();

        $skills = $repository->findByIds([$second->id, $first->id], 'en');

        expect(array_map(
            static fn (TechSkillDTO $skill): int => $skill->id,
            $skills,
        ))->toBe([$first->id, $second->id]);
    });

    it('returns an empty list for unknown or empty ids', function (): void {
        $repository = new EloquentTechSkillReadRepository();

        expect($repository->findByIds([999_999], 'en'))->toBe([])
            ->and($repository->findByIds([], 'en'))->toBe([]);
    });

    it('returns an empty list when no skills exist', function (): void {
        $repository = new EloquentTechSkillReadRepository();

        expect($repository->findAll('en'))->toBe([])
            ->and($repository->findAllGrouped('en'))->toBe([]);
    });

    it('groups skills by group preserving group and skill order', function (): void {
        $backend = createSkillGroup(1, 'heroicons:server');
        translateGroup($backend, 'en', 'Backend');
        $frontend = createSkillGroup(2, 'heroicons:code-bracket');
        translateGroup($frontend, 'en', 'Frontend');

        createSkill($backend, 'PHP', 4, 1);
        createSkill($backend, 'Laravel', 5, 2);
        createSkill($frontend, 'Vue.js', 4, 1);

        $repository = new EloquentTechSkillReadRepository();

        $grouped = $repository->findAllGrouped('en');

        expect($grouped)->toHaveCount(2)
            ->and($grouped[0])->toBeInstanceOf(GroupedTechSkillsDTO::class)
            ->and($grouped[0]->group->name)->toBe('Backend')
            ->and($grouped[0]->group->icon)->toBe('heroicons:server')
            ->and(array_map(
                static fn (TechSkillDTO $skill): string => $skill->name,
                $grouped[0]->skills,
            ))->toBe(['PHP', 'Laravel'])
            ->and($grouped[1]->group->name)->toBe('Frontend')
            ->and(array_map(
                static fn (TechSkillDTO $skill): string => $skill->name,
                $grouped[1]->skills,
            ))->toBe(['Vue.js']);
    });
});
