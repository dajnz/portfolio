<?php

declare(strict_types=1);

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\ValueObjects\ProficiencyLevel;
use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;
use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;

/**
 * @return TechSkillGroup
 */
function makeGroup(array $overrides = []): TechSkillGroup
{
    return new TechSkillGroup(
        id: $overrides['id'] ?? 1,
        icon: $overrides['icon'] ?? new SkillGroupIcon('heroicons:server'),
        sortOrder: $overrides['sort_order'] ?? new SortOrder(0),
        groupNames: $overrides['group_names'] ?? [
            new TechSkillGroupName(locale: 'en', name: 'Backend'),
            new TechSkillGroupName(locale: 'ru', name: 'Бэкенд'),
        ],
        skills: $overrides['skills'] ?? [],
    );
}

/**
 * @return TechSkill
 */
function makeGroupSkill(int $id, string $name): TechSkill
{
    return new TechSkill(
        id: $id,
        name: $name,
        icon: new SkillIcon('devicon:laravel'),
        proficiency: new ProficiencyLevel(4),
        includeInResume: true,
        sortOrder: new SortOrder(0),
        group: new SkillGroupVo(id: 1, groupNames: []),
    );
}

describe('TechSkillGroup', function (): void {
    it('exposes its state via toXXX getters', function (): void {
        $group = makeGroup();

        expect($group->toId())->toBe(1)
            ->and($group->toIcon())->toBe('heroicons:server')
            ->and($group->toSortOrder())->toBe(0)
            ->and($group->toGroupNames())->toHaveCount(2)
            ->and($group->groupNamesByLocale())->toBe(['en' => 'Backend', 'ru' => 'Бэкенд'])
            ->and($group->nameFor('ru'))->toBe('Бэкенд')
            ->and($group->nameFor('de'))->toBe('Backend')
            ->and($group->toSkills())->toBe([]);
    });

    it('renames a locale keeping the other locales intact', function (): void {
        $group = makeGroup();

        $group->rename('New Backend', 'en');

        expect($group->nameFor('en'))->toBe('New Backend')
            ->and($group->nameFor('ru'))->toBe('Бэкенд');
    });

    it('adds a missing locale on rename', function (): void {
        $group = makeGroup();

        $group->rename('Фронтенд', 'fr');

        expect($group->nameFor('fr'))->toBe('Фронтенд')
            ->and($group->toGroupNames())->toHaveCount(3);
    });

    it('changes the icon', function (): void {
        $group = makeGroup();

        $group->changeIcon(new SkillGroupIcon('heroicons:code-bracket'));

        expect($group->toIcon())->toBe('heroicons:code-bracket');
    });

    it('rearranges the group itself', function (): void {
        $group = makeGroup();

        $group->rearrange(new SortOrder(2));

        expect($group->toSortOrder())->toBe(2);
    });

    it('builds its SkillGroupVo with the current names', function (): void {
        $group = makeGroup();

        $vo = $group->toGroupVo();

        expect($vo)->toBeInstanceOf(SkillGroupVo::class)
            ->and($vo->toId())->toBe(1)
            ->and($vo->toGroupNames())->toHaveCount(2)
            ->and($vo->groupNameFor('ru'))->toBe('Бэкенд');
    });

    it('adds a skill by moving it into the group at the given order', function (): void {
        $group = makeGroup();
        $skill = makeGroupSkill(10, 'PHP');

        $group->addSkill($skill, new SortOrder(4));

        expect($group->toSkills())->toHaveCount(1)
            ->and($group->toSkills()[0]->toId())->toBe(10)
            ->and($skill->toGroupId())->toBe(1)
            ->and($skill->toGroupName('en'))->toBe('Backend')
            ->and($skill->toSortOrder())->toBe(4);
    });

    it('removes a skill from the internal collection', function (): void {
        $skillA = makeGroupSkill(10, 'PHP');
        $skillB = makeGroupSkill(11, 'Laravel');
        $group = makeGroup(['skills' => [$skillA, $skillB]]);

        $group->removeSkill($skillA);

        expect(array_map(
            static fn (TechSkill $skill): int => $skill->toId(),
            $group->toSkills(),
        ))->toBe([11]);
    });

    it('indexes skills by id on construction', function (): void {
        $group = makeGroup(['skills' => [makeGroupSkill(10, 'PHP'), makeGroupSkill(11, 'Laravel')]]);

        $group->removeSkill(makeGroupSkill(10, 'PHP'));

        expect(array_map(
            static fn (TechSkill $skill): int => $skill->toId(),
            $group->toSkills(),
        ))->toBe([11]);
    });
});
