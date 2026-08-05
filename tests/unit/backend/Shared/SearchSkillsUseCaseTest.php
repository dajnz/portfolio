<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\App\UseCases\SearchSkillsUseCase;
use App\Features\Shared\Domain\Entities\TechSkill;
use Tests\Fakes\InMemoryTechSkillReadRepository;

/**
 * @return TechSkill[]
 */
function buildSkills(array $names): array
{
    $skills = [];

    foreach ($names as $id => $name) {
        $skills[] = new TechSkill(
            id: $id,
            name: $name,
            icon: 'devicon:test',
            proficiency: 3,
            sortOrder: 0,
            groupId: 1,
            groupName: 'Backend',
        );
    }

    return $skills;
}

describe('SearchSkillsUseCase', function (): void {
    it('returns no results for an empty query', function (): void {
        $useCase = new SearchSkillsUseCase(
            new InMemoryTechSkillReadRepository(buildSkills(['PHP', 'Laravel'])),
        );

        expect($useCase->search('', 'en'))->toBe([]);
    });

    it('matches skill names case-insensitively by substring', function (): void {
        $useCase = new SearchSkillsUseCase(
            new InMemoryTechSkillReadRepository(buildSkills(['Laravel', 'PHP', 'Laravel Mix'])),
        );

        $results = $useCase->search('LAR', 'en');

        expect(array_map(static fn (TechSkill $skill): string => $skill->toName(), $results))
            ->toBe(['Laravel', 'Laravel Mix']);
    });

    it('orders matches by name ascending', function (): void {
        $useCase = new SearchSkillsUseCase(
            new InMemoryTechSkillReadRepository(buildSkills(['TypeScript', 'Tailwind', 'Tiptap'])),
        );

        $results = $useCase->search('t', 'en');

        expect(array_map(static fn (TechSkill $skill): string => $skill->toName(), $results))
            ->toBe(['Tailwind', 'Tiptap', 'TypeScript']);
    });

    it('orders matches by name ascending for non-ASCII names', function (): void {
        $useCase = new SearchSkillsUseCase(
            new InMemoryTechSkillReadRepository(buildSkills(['Эксперт', 'Архитектор', 'Инженер'])),
        );

        $results = $useCase->search('е', 'en');

        expect(array_map(static fn (TechSkill $skill): string => $skill->toName(), $results))
            ->toBe(['Архитектор', 'Инженер', 'Эксперт']);
    });

    it('caps the result list at 20 items', function (): void {
        $names = [];
        for ($i = 1; $i <= 25; $i++) {
            $names[] = "Skill {$i}";
        }

        $useCase = new SearchSkillsUseCase(new InMemoryTechSkillReadRepository(buildSkills($names)));

        $results = $useCase->search('skill', 'en');

        expect(count($results))->toBe(20)
            ->and(array_map(static fn (TechSkill $skill): string => $skill->toName(), $results))
            ->toBe([
                'Skill 1', 'Skill 10', 'Skill 11', 'Skill 12', 'Skill 13', 'Skill 14', 'Skill 15',
                'Skill 16', 'Skill 17', 'Skill 18', 'Skill 19', 'Skill 2', 'Skill 20', 'Skill 21',
                'Skill 22', 'Skill 23', 'Skill 24', 'Skill 25', 'Skill 3', 'Skill 4',
            ]);
    });

    it('returns the skill entities of the matches', function (): void {
        $useCase = new SearchSkillsUseCase(
            new InMemoryTechSkillReadRepository(buildSkills(['Laravel', 'PHP'])),
        );

        $results = $useCase->search('laravel', 'en');

        expect($results)->toHaveCount(1)
            ->and($results[0])->toBeInstanceOf(TechSkill::class)
            ->and($results[0]->toId())->toBe(0)
            ->and($results[0]->toName())->toBe('Laravel')
            ->and($results[0]->toIcon())->toBe('devicon:test');
    });

    it('passes the locale through to the repository', function (): void {
        $repository = new InMemoryTechSkillReadRepository(buildSkills(['Laravel']));
        $useCase = new SearchSkillsUseCase($repository);

        $useCase->search('laravel', 'ru');

        expect($repository->lastLocale)->toBe('ru');
    });
});
