<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\ProficiencyLevel;

describe('ProficiencyLevel', function (): void {
    it('exposes the 1-5 level', function (): void {
        $level = new ProficiencyLevel(4);

        expect($level->toValue())->toBe(4);
    });

    it('rejects out-of-range levels', function (): void {
        new ProficiencyLevel(0);
    })->throws(InvalidArgumentException::class);

    it('compares levels by value', function (): void {
        expect((new ProficiencyLevel(4))->equals(new ProficiencyLevel(4)))->toBeTrue()
            ->and((new ProficiencyLevel(4))->equals(new ProficiencyLevel(5)))->toBeFalse();
    });
});
