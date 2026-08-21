<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Factories;

use App\Features\Analytics\Domain\Entities\AnalyticsEvent;
use App\Features\Analytics\Domain\Entities\ArticleReadEvent;
use App\Features\Analytics\Domain\Entities\ArticleReactionEvent;
use App\Features\Analytics\Domain\Entities\CallToActionClickEvent;
use App\Features\Analytics\Domain\Entities\LanguageSwitchEvent;
use App\Features\Analytics\Domain\Entities\PageViewEvent;
use App\Features\Analytics\Domain\Entities\ThemeSwitchEvent;
use App\Features\Analytics\Domain\Entities\TimeOnPageEvent;
use App\Features\Analytics\Domain\Enums\CallToActionTarget;
use App\Features\Analytics\Domain\Enums\SocialReaction;
use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use DateTimeImmutable;
use InvalidArgumentException;
use TypeError;

/**
 * Parses the stored `analytics_events` rows into the typed event
 * entities — the single dispatch table for the event types. The client
 * identifiers and the page context arrive pre-built as their VOs.
 * Returns null for rows that cannot be materialized: unknown event
 * types (forward compatibility — ingestion rejects them, but legacy or
 * hand-seeded rows may exist) and malformed payload metadata (the old
 * SQL aggregation effectively skipped those too). An unknown page type
 * is tolerated (null) — the page context is informational for the
 * reports, the event itself is still valid.
 *
 * The plain CTA clicks are identified by their `cta_click_{target}`
 * type suffix (B287 — the frontend sends no payload for them); the
 * language/theme switches (also `cta_click_*` types) resolve first
 * through the match below.
 */
final class AnalyticsEventFactory
{
    private const CTA_CLICK_PREFIX = 'cta_click_';

    public static function fromRow(
        int $id,
        string $eventType,
        ClientIds $clientIds,
        PageDetails $pageDetails,
        ?array $metadata,
        DateTimeImmutable $createdAt,
    ): ?AnalyticsEvent {
        $metadata ??= [];

        try {
            $target = str_starts_with($eventType, self::CTA_CLICK_PREFIX)
                ? CallToActionTarget::tryFrom(substr($eventType, strlen(self::CTA_CLICK_PREFIX)))
                : null;

            if ($target !== null) {
                return new CallToActionClickEvent(
                    id: $id,
                    clientIds: $clientIds,
                    pageDetails: $pageDetails,
                    target: $target,
                    createdAt: $createdAt,
                );
            }

            return match ($eventType) {
                PageViewEvent::EVENT_TYPE => new PageViewEvent(
                    id: $id,
                    clientIds: $clientIds,
                    pageDetails: $pageDetails,
                    referrer: $metadata['referrer'] ?? null,
                    title: $metadata['title'] ?? null,
                    createdAt: $createdAt,
                ),
                TimeOnPageEvent::EVENT_TYPE => new TimeOnPageEvent(
                    id: $id,
                    clientIds: $clientIds,
                    pageDetails: $pageDetails,
                    seconds: (int) ($metadata['seconds'] ?? -1),
                    createdAt: $createdAt,
                ),
                LanguageSwitchEvent::EVENT_TYPE => new LanguageSwitchEvent(
                    id: $id,
                    clientIds: $clientIds,
                    pageDetails: $pageDetails,
                    locale: (string) ($metadata['locale'] ?? ''),
                    createdAt: $createdAt,
                ),
                ThemeSwitchEvent::EVENT_TYPE => new ThemeSwitchEvent(
                    id: $id,
                    clientIds: $clientIds,
                    pageDetails: $pageDetails,
                    mode: $metadata['mode'] ?? null,
                    createdAt: $createdAt,
                ),
                ArticleReadEvent::EVENT_TYPE => new ArticleReadEvent(
                    id: $id,
                    clientIds: $clientIds,
                    pageDetails: $pageDetails,
                    articleId: (int) ($metadata['article_id'] ?? 0),
                    percentageCompleted: (int) ($metadata['percentage_completed'] ?? -1),
                    createdAt: $createdAt,
                ),
                ArticleReactionEvent::EVENT_TYPE => new ArticleReactionEvent(
                    id: $id,
                    clientIds: $clientIds,
                    pageDetails: $pageDetails,
                    articleId: (int) ($metadata['article_id'] ?? 0),
                    reaction: SocialReaction::tryFrom((string) ($metadata['reaction'] ?? ''))
                        ?? throw new InvalidArgumentException('Unknown reaction value.'),
                    createdAt: $createdAt,
                ),
                default => null,
            };
        } catch (InvalidArgumentException | TypeError) {
            // Malformed metadata — the entity invariants reject it. The
            // row is skipped rather than failing the whole aggregation.
            return null;
        }
    }
}
