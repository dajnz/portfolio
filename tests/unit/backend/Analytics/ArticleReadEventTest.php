<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\ArticleReadEvent;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\Support\AnalyticsEventFixture;

/**
 * The `article_read` event invariants (analytics.md:25): article id and completion percentage.
 */
describe('ArticleReadEvent', function (): void {
    it('rejects a non-positive article id', function (): void {
        expect(fn () => new ArticleReadEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            articleId: 0,
            percentageCompleted: 50,
            createdAt: new DateTimeImmutable(),
        ))->toThrow(InvalidArgumentException::class, 'must be a positive integer');
    });

    it('rejects a percentage outside the 0-100 range', function (): void {
        expect(fn () => new ArticleReadEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            articleId: 1,
            percentageCompleted: 101,
            createdAt: new DateTimeImmutable(),
        ))->toThrow(InvalidArgumentException::class, 'between 0 and 100');
    });

    it('serializes the article id and percentage', function (): void {
        $event = new ArticleReadEvent(
            id: 0,
            clientIds: AnalyticsEventFixture::clientIds(),
            pageDetails: AnalyticsEventFixture::pageDetails(),
            articleId: 5,
            percentageCompleted: 80,
            createdAt: new DateTimeImmutable(),
        );

        expect($event->toEventSpecificData())->toBe(['article_id' => 5, 'percentage_completed' => 80]);
    });
});
