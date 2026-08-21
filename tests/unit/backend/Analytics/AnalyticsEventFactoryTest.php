<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Entities\ArticleReadEvent;
use App\Features\Analytics\Domain\Entities\ArticleReactionEvent;
use App\Features\Analytics\Domain\Entities\CallToActionClickEvent;
use App\Features\Analytics\Domain\Entities\LanguageSwitchEvent;
use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Entities\ThemeSwitchEvent;
use App\Features\Analytics\Domain\Entities\TimeOnPageEvent;
use App\Features\Analytics\Domain\Enums\CallToActionTarget;
use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Domain\Enums\SocialReaction;
use App\Features\Analytics\Domain\Factories\AnalyticsEventFactory;
use DateTimeImmutable;
use Tests\Support\AnalyticsEventFixture;

/**
 * The metadata parser (B287/B289): every stored row shape materializes
 * into its typed entity; unknown event types and malformed payloads
 * yield null (skipped by the reads, rejected by the ingestion). An
 * unknown page type is tolerated (null) — the page context is
 * informational for the reports.
 */

/**
 * Parses a minimal row of the given shape.
 */
function parseAnalyticsEventRow(
    string $eventType,
    array $metadata = [],
    ?PageType $pageType = null,
    ?int $pageEntityId = null,
): mixed {
    return AnalyticsEventFactory::fromRow(
        id: 1,
        eventType: $eventType,
        clientIds: AnalyticsEventFixture::clientIds(),
        pageDetails: AnalyticsEventFixture::pageDetails($pageType, $pageEntityId),
        metadata: $metadata,
        createdAt: new DateTimeImmutable('2026-07-09 10:00:00'),
    );
}

describe('AnalyticsEventFactory', function (): void {
    it('parses a page_view row into its typed entity', function (): void {
        $event = parseAnalyticsEventRow('page_view', ['referrer' => null, 'title' => 'Hello']);

        expect($event)->toBeInstanceOf(PageViewEvent::class)
            ->and($event->toReferrer())->toBeNull()
            ->and($event->toTitle())->toBe('Hello');
    });

    it('parses a time_on_page row with the page context', function (): void {
        $event = parseAnalyticsEventRow('time_on_page', ['seconds' => 12], PageType::BlogArticle, 5);

        expect($event)->toBeInstanceOf(TimeOnPageEvent::class)
            ->and($event->toSeconds())->toBe(12)
            ->and($event->toPageDetails()->toBlogArticleId())->toBe(5);
    });

    it('parses the plain cta clicks from their type suffix', function (): void {
        foreach (CallToActionTarget::cases() as $target) {
            $event = parseAnalyticsEventRow('cta_click_'.$target->value);

            expect($event)->toBeInstanceOf(CallToActionClickEvent::class)
                ->and($event->toTarget())->toBe($target)
                ->and($event->eventType())->toBe('cta_click_'.$target->value);
        }
    });

    it('parses the language and theme switches', function (): void {
        $language = parseAnalyticsEventRow('cta_click_language', ['locale' => 'ru']);
        $theme = parseAnalyticsEventRow('cta_click_theme', ['mode' => 'dark']);

        expect($language)->toBeInstanceOf(LanguageSwitchEvent::class)
            ->and($language->toLocale())->toBe('ru')
            ->and($theme)->toBeInstanceOf(ThemeSwitchEvent::class)
            ->and($theme->toMode())->toBe('dark');
    });

    it('parses an article_read row', function (): void {
        $event = parseAnalyticsEventRow('article_read', ['article_id' => 5, 'percentage_completed' => 80]);

        expect($event)->toBeInstanceOf(ArticleReadEvent::class)
            ->and($event->toArticleId())->toBe(5)
            ->and($event->toPercentageCompleted())->toBe(80);
    });

    it('parses an article_reaction row', function (): void {
        $event = parseAnalyticsEventRow('article_reaction', ['article_id' => 5, 'reaction' => 'like']);

        expect($event)->toBeInstanceOf(ArticleReactionEvent::class)
            ->and($event->toArticleId())->toBe(5)
            ->and($event->toReaction())->toBe(SocialReaction::Like);
    });

    it('returns null for unknown event types', function (): void {
        expect(parseAnalyticsEventRow('garbage_type'))->toBeNull();
    });

    it('returns null for malformed time_on_page metadata', function (): void {
        expect(parseAnalyticsEventRow('time_on_page', []))->toBeNull();
    });

    it('returns null for malformed article_read metadata', function (): void {
        expect(parseAnalyticsEventRow('article_read', []))->toBeNull();
    });

    it('returns null for an unknown reaction value', function (): void {
        expect(parseAnalyticsEventRow('article_reaction', ['article_id' => 1, 'reaction' => 'meh']))->toBeNull();
    });

    it('returns null for an unsupported language locale', function (): void {
        expect(parseAnalyticsEventRow('cta_click_language', ['locale' => 'de']))->toBeNull();
    });

    it('tolerates an unknown page type', function (): void {
        $event = parseAnalyticsEventRow('page_view');

        expect($event)->toBeInstanceOf(PageViewEvent::class)
            ->and($event->toPageDetails()->toPageType())->toBeNull();
    });
});
