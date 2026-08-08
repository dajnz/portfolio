<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\SkillIcon;

describe('SkillIcon', function (): void {
    it('exposes the devicon identifier', function (): void {
        $icon = new SkillIcon('devicon:laravel');

        expect($icon->toValue())->toBe('devicon:laravel');
    });

    it('rejects a value outside the devicon set', function (): void {
        new SkillIcon('heroicons:server');
    })->throws(InvalidArgumentException::class);

    it('rejects malformed identifiers', function (): void {
        new SkillIcon('no-colon');
    })->throws(InvalidArgumentException::class);

    it('rejects an empty value', function (): void {
        new SkillIcon('');
    })->throws(InvalidArgumentException::class);
});
