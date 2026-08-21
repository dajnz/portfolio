<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\CallToActionClickEvent;
use App\Features\Analytics\Domain\Enums\CallToActionTarget;
use DateTimeImmutable;
use Tests\Support\AnalyticsEventFixture;

/**
 * The shared event state (B289): the client identifiers and the page
 * context are carried as their VOs (their validation lives there) —
 * the base exposes the delegating getters and the event-type identity.
 */
describe('AnalyticsEvent', function (): void {
    it('exposes the shared state and the event type identity', function (): void {
        $event = new CallToActionClickEvent(
            id: 7,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            target: CallToActionTarget::Cv,
            createdAt: new DateTimeImmutable('2026-07-09 10:00:00'),
        );

        expect($event->toId())->toBe(7)
            ->and($event->eventType())->toBe('cta_click_cv')
            ->and($event->toSessionId())->toBe('s-1')
            ->and($event->toIpAddress())->toBe('ip-hash')
            ->and($event->toUserAgent())->toBe('Mozilla/5.0 Chrome/126.0')
            ->and($event->toPageUrl())->toBe('https://example.com/')
            ->and($event->toClientIds())->toBe($event->toClientIds())
            ->and($event->toPageDetails()->toPageType())->toBeNull()
            ->and($event->toCreatedAt())->toEqual(new DateTimeImmutable('2026-07-09 10:00:00'));
    });
});
