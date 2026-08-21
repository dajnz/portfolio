<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Enums\PageType;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\Support\AnalyticsEventFixture;

/**
 * The `page_view` event invariants (analytics.md:17): the referrer cap
 * and the payload round-trip.
 */
describe('PageViewEvent', function (): void {
    it('rejects a referrer longer than 2048 characters', function (): void {
        expect(fn () => new PageViewEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            referrer: str_repeat('r', 2049),
            title: 'Hello',
            createdAt: new DateTimeImmutable(),
        ))->toThrow(InvalidArgumentException::class, 'must not exceed 2048 characters');
    });

    it('serializes the referrer and title into the event-specific data', function (): void {
        $event = new PageViewEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            referrer: null,
            title: 'Hello',
            createdAt: new DateTimeImmutable(),
        );

        expect($event->toEventSpecificData())->toBe(['referrer' => null, 'title' => 'Hello']);
    });

    it('exposes the client ids and the page details', function (): void {
        $event = new PageViewEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello'),
            referrer: null,
            title: null,
            createdAt: new DateTimeImmutable(),
        );

        expect($event->toSessionId())->toBe('s-1')
            ->and($event->toIpAddress())->toBe('ip-hash')
            ->and($event->toPageUrl())->toBe('https://example.com/blog/hello')
            ->and($event->toPageDetails()->toBlogArticleId())->toBe(5);
    });
});
