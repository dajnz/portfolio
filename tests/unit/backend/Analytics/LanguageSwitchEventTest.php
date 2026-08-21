<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\LanguageSwitchEvent;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\Support\AnalyticsEventFixture;

/**
 * The `cta_click_language` event invariants (analytics.md:23): the locale whitelist.
 */
describe('LanguageSwitchEvent', function (): void {
    it('rejects an unsupported locale', function (): void {
        expect(fn () => new LanguageSwitchEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            locale: 'de',
            createdAt: new DateTimeImmutable(),
        ))->toThrow(InvalidArgumentException::class, 'Locale must be one of: en, ru.');
    });

    it('serializes the locale into the event-specific data', function (): void {
        $event = new LanguageSwitchEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            locale: 'ru',
            createdAt: new DateTimeImmutable(),
        );

        expect($event->toEventSpecificData())->toBe(['target' => 'language', 'locale' => 'ru']);
    });
});
