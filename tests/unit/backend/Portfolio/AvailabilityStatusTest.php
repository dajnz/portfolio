<?php

declare(strict_types=1);

use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;

describe('AvailabilityStatus', function (): void {
    it('exposes the three documented statuses', function (): void {
        expect(AvailabilityStatus::cases())->toHaveCount(3)
            ->and(AvailabilityStatus::Available->value)->toBe('available')
            ->and(AvailabilityStatus::Limited->value)->toBe('limited')
            ->and(AvailabilityStatus::Busy->value)->toBe('busy');
    });

    it('exposes the static-translation label keys (no UI concerns)', function (): void {
        expect(AvailabilityStatus::Available->toLabelKey())->toBe('landing.availability.available')
            ->and(AvailabilityStatus::Limited->toLabelKey())->toBe('landing.availability.limited')
            ->and(AvailabilityStatus::Busy->toLabelKey())->toBe('landing.availability.busy');
    });

    it('round-trips from the stored raw value', function (): void {
        foreach (['available', 'limited', 'busy'] as $raw) {
            expect(AvailabilityStatus::from($raw))->toBeInstanceOf(AvailabilityStatus::class);
        }
    });
});
