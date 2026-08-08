<?php

declare(strict_types=1);

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\ValueObjects\ProficiencyLevel;
use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;

/**
 * @return TechSkill
 */
function makeSkill(array $overrides = []): TechSkill
{
    return new TechSkill(
        id: $overrides['id'] ?? 1,
        name: $overrides['name'] ?? 'Laravel',
        icon: $overrides['icon'] ?? new SkillIcon('devicon:laravel'),
        proficiency: $overrides['proficiency'] ?? new ProficiencyLevel(4),
        includeInResume: $overrides['include_in_resume'] ?? true,
        sortOrder: $overrides['sort_order'] ?? new SortOrder(0),
        group: $overrides['group'] ?? new SkillGroupVo(id: 1, groupNames: [
            new TechSkillGroupName(locale: 'en', name: 'Backend'),
            new TechSkillGroupName(locale: 'ru', name: 'Бэкенд'),
        ]),
    );
}

describe('TechSkill', function (): void {
    it('exposes its state via toXXX getters', function (): void {
        $skill = makeSkill();

        expect($skill->toId())->toBe(1)
            ->and($skill->toName())->toBe('Laravel')
            ->and($skill->toIcon())->toBe('devicon:laravel')
            ->and($skill->toProficiency())->toBe(4)
            ->and($skill->toProficiencyLabelKey())->toBe('skills.proficiency.4')
            ->and($skill->toIncludeInResume())->toBeTrue()
            ->and($skill->toSortOrder())->toBe(0)
            ->and($skill->toGroupId())->toBe(1)
            ->and($skill->toGroupName('ru'))->toBe('Бэкенд')
            ->and($skill->toGroupName('de'))->toBe('Backend');
    });

    it('rejects an empty name', function (): void {
        makeSkill(['name' => '']);
    })->throws(InvalidArgumentException::class);

    it('renames the skill', function (): void {
        $skill = makeSkill();

        $skill->rename('PHP');

        expect($skill->toName())->toBe('PHP');
    });

    it('rejects an empty rename', function (): void {
        makeSkill()->rename('');
    })->throws(InvalidArgumentException::class);

    it('changes the icon', function (): void {
        $skill = makeSkill();

        $skill->changeIcon(new SkillIcon('devicon:php'));

        expect($skill->toIcon())->toBe('devicon:php');
    });

    it('changes the proficiency', function (): void {
        $skill = makeSkill();

        $skill->changeProficiency(new ProficiencyLevel(5));

        expect($skill->toProficiency())->toBe(5)
            ->and($skill->toProficiencyLabelKey())->toBe('skills.proficiency.5');
    });

    it('toggles the include-in-resume flag', function (): void {
        $skill = makeSkill(['include_in_resume' => false]);

        $skill->useInResume();
        expect($skill->toIncludeInResume())->toBeTrue();

        $skill->unuseInResume();
        expect($skill->toIncludeInResume())->toBeFalse();
    });

    it('moves the skill to another group at the given order', function (): void {
        $skill = makeSkill();

        $targetGroup = new SkillGroupVo(id: 2, groupNames: [
            new TechSkillGroupName(locale: 'en', name: 'Frontend'),
        ]);

        $skill->changeGroup($targetGroup, new SortOrder(3));

        expect($skill->toGroupId())->toBe(2)
            ->and($skill->toGroupName('en'))->toBe('Frontend')
            ->and($skill->toSortOrder())->toBe(3);
    });
});
