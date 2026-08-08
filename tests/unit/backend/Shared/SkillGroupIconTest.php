<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\SkillGroupIcon;

describe('SkillGroupIcon', function (): void {
    it('exposes the heroicons identifier', function (): void {
        $icon = new SkillGroupIcon('heroicons:server');

        expect($icon->toValue())->toBe('heroicons:server');
    });

    it('rejects a value outside the heroicons set', function (): void {
        new SkillGroupIcon('devicon:laravel');
    })->throws(InvalidArgumentException::class);

    it('rejects an empty value', function (): void {
        new SkillGroupIcon('');
    })->throws(InvalidArgumentException::class);
});
