<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\CallToActionClickEvent;
use App\Features\Analytics\Domain\Enums\CallToActionTarget;
use DateTimeImmutable;
use Tests\Support\AnalyticsEventFixture;

describe('CallToActionClickEvent', function (): void {
    it('derives the event type from the target', function (): void {
        expect((new CallToActionClickEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            target: CallToActionTarget::Github,
            createdAt: new DateTimeImmutable(),
        ))->eventType())->toBe('cta_click_github');
    });

    it('serializes the target into the event-specific data', function (): void {
        $event = new CallToActionClickEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            target: CallToActionTarget::Cv,
            createdAt: new DateTimeImmutable(),
        );

        expect($event->toEventSpecificData())->toBe(['target' => 'cv']);
    });
});
