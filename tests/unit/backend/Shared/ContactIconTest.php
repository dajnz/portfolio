<?php

declare(strict_types=1);

use App\Features\Shared\Domain\ValueObjects\ContactIcon;

describe('ContactIcon', function (): void {
    it('exposes the generic iconify identifier', function (): void {
        $icon = new ContactIcon('simple-icons:github');

        expect($icon->toValue())->toBe('simple-icons:github');
    });

    it('rejects malformed identifiers', function (): void {
        new ContactIcon('no-colon');
    })->throws(InvalidArgumentException::class);

    it('rejects an empty value', function (): void {
        new ContactIcon('');
    })->throws(InvalidArgumentException::class);
});
