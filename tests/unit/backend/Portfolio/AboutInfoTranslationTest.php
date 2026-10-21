<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Portfolio;

use App\Features\Portfolio\Domain\ValueObjects\AboutInfoTranslation;
use InvalidArgumentException;

describe('AboutInfoTranslation', function (): void {
    it('exposes its fields', function (): void {
        $translation = new AboutInfoTranslation(
            locale: 'en',
            name: 'Alex',
            title: 'Developer',
            shortPitch: 'Pitch.',
            location: null,
        );

        expect($translation->toLocale())->toBe('en')
            ->and($translation->toName())->toBe('Alex')
            ->and($translation->toTitle())->toBe('Developer')
            ->and($translation->toShortPitch())->toBe('Pitch.')
            ->and($translation->toLocation())->toBeNull();
    });

    it('rejects an empty name or title', function (): void {
        new AboutInfoTranslation(locale: 'en', name: '', title: 'Dev', shortPitch: 'P.', location: null);
    })->throws(InvalidArgumentException::class);
});
