<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;

describe('TechSkillGroupName', function (): void {
    it('exposes locale and name', function (): void {
        $name = new TechSkillGroupName(locale: 'en', name: 'Backend');

        expect($name->toLocale())->toBe('en')
            ->and($name->toName())->toBe('Backend');
    });

    it('rejects an empty name', function (): void {
        new TechSkillGroupName(locale: 'en', name: '');
    })->throws(InvalidArgumentException::class);
});
