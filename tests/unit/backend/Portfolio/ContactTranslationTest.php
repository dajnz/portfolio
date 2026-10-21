<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Portfolio;

use App\Features\Portfolio\Domain\ValueObjects\ContactTranslation;
use InvalidArgumentException;

describe('ContactTranslation', function (): void {
    it('exposes locale and name', function (): void {
        $translation = new ContactTranslation(locale: 'en', name: 'GitHub');

        expect($translation->toLocale())->toBe('en')
            ->and($translation->toName())->toBe('GitHub');
    });

    it('rejects an empty name', function (): void {
        new ContactTranslation(locale: 'en', name: '');
    })->throws(InvalidArgumentException::class);
});
