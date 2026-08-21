<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\ThemeSwitchEvent;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\Support\AnalyticsEventFixture;

/**
 * The `cta_click_theme` event invariants (analytics.md:24): the mode is validated when present.
 */
describe('ThemeSwitchEvent', function (): void {
    it('rejects an unsupported mode', function (): void {
        expect(fn () => new ThemeSwitchEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            mode: 'sepia',
            createdAt: new DateTimeImmutable(),
        ))->toThrow(InvalidArgumentException::class, 'Mode must be one of: light, dark.');
    });

    it('accepts a missing mode', function (): void {
        $event = new ThemeSwitchEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            mode: null,
            createdAt: new DateTimeImmutable(),
        );

        expect($event->toMode())->toBeNull()
            ->and($event->toEventSpecificData())->toBe(['target' => 'theme', 'mode' => null]);
    });
});
