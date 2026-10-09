<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\ArticleReactionEvent;
use App\Features\Analytics\Domain\Enums\SocialReaction;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\Support\AnalyticsEventFixture;

describe('ArticleReactionEvent', function (): void {
    it('rejects a non-positive article id', function (): void {
        expect(fn () => new ArticleReactionEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            articleId: 0,
            reaction: SocialReaction::Like,
            createdAt: new DateTimeImmutable(),
        ))->toThrow(InvalidArgumentException::class, 'must be a positive integer');
    });

    it('serializes the reaction', function (): void {
        $event = new ArticleReactionEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            articleId: 5,
            reaction: SocialReaction::Dislike,
            createdAt: new DateTimeImmutable(),
        );

        expect($event->toReaction())->toBe(SocialReaction::Dislike)
            ->and($event->toEventSpecificData())->toBe(['article_id' => 5, 'reaction' => 'dislike']);
    });
});
