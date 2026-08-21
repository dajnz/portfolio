<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\TimeOnPageEvent;
use App\Features\Analytics\Domain\Enums\PageType;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\Support\AnalyticsEventFixture;

/**
 * The `time_on_page` event invariants (analytics.md:18): the seconds range and the payload round-trip.
 */
describe('TimeOnPageEvent', function (): void {
    it('rejects seconds outside the 0-86400 range', function (): void {
        expect(fn () => new TimeOnPageEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            seconds: 86401,
            createdAt: new DateTimeImmutable(),
        ))->toThrow(InvalidArgumentException::class, 'between 0 and 86400');
    });

    it('serializes the seconds and the departing page url', function (): void {
        $event = new TimeOnPageEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            seconds: 12,
            createdAt: new DateTimeImmutable(),
        );

        expect($event->toSeconds())->toBe(12)
            ->and($event->toEventSpecificData())->toBe(['seconds' => 12, 'page_url' => 'https://example.com/blog/hello']);
    });
});
