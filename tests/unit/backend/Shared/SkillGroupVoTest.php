<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\SkillGroupVo;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;

describe('SkillGroupVo', function (): void {
    it('exposes the group id and its names', function (): void {
        $vo = new SkillGroupVo(id: 2, groupNames: [
            new TechSkillGroupName(locale: 'en', name: 'Backend'),
            new TechSkillGroupName(locale: 'ru', name: 'Бэкенд'),
        ]);

        expect($vo->toId())->toBe(2)
            ->and($vo->toGroupNames())->toHaveCount(2);
    });

    it('resolves the localized name with an en fallback', function (): void {
        $vo = new SkillGroupVo(id: 2, groupNames: [
            new TechSkillGroupName(locale: 'en', name: 'Backend'),
            new TechSkillGroupName(locale: 'ru', name: 'Бэкенд'),
        ]);

        expect($vo->groupNameFor('ru'))->toBe('Бэкенд')
            ->and($vo->groupNameFor('en'))->toBe('Backend')
            ->and($vo->groupNameFor('de'))->toBe('Backend');
    });

    it('returns an empty name when no translation exists at all', function (): void {
        $vo = new SkillGroupVo(id: 2, groupNames: []);

        expect($vo->groupNameFor('en'))->toBe('');
    });
});
