<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Mappers;

use App\Features\Analytics\Domain\Entities\AnalyticsEvent;
use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Domain\Factories\AnalyticsEventFactory;
use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use App\Features\Analytics\Infra\Models\AnalyticsEventModel;

/**
 * Converts between `analytics_events` rows and the typed event
 * entities: `toRow` serializes the concrete properties (incl. the
 * client identifiers and the page context columns) back into the
 * stored row, `toEntity` parses the row through
 * {@see AnalyticsEventFactory} (null for rows the parser cannot
 * materialize). `created_at` is left to the database default on
 * writes — events are append-only.
 */
final class AnalyticsEventMapper
{
    /**
     * @return array{
     *     event_type: string,
     *     session_id: string,
     *     page_type: ?string,
     *     page_entity_id: ?int,
     *     page_url: ?string,
     *     metadata: ?array<string, mixed>,
     *     ip_address: ?string,
     *     user_agent: ?string,
     * }
     */
    public function toRow(AnalyticsEvent $event): array
    {
        $pageType = $event->toPageDetails()->toPageType();

        return [
            'event_type' => $event->eventType(),
            'session_id' => $event->toSessionId(),
            'page_type' => $pageType?->value,
            'page_entity_id' => $event->toPageDetails()->toPageEntityId(),
            'page_url' => $event->toPageUrl(),
            'metadata' => $event->toEventSpecificData(),
            'ip_address' => $event->toIpAddress(),
            'user_agent' => $event->toUserAgent(),
        ];
    }

    public function toEntity(AnalyticsEventModel $model): ?AnalyticsEvent
    {
        return AnalyticsEventFactory::fromRow(
            id: $model->id,
            eventType: $model->event_type,
            clientIds: new ClientIds(
                sessionId: $model->session_id,
                ipAddress: $model->ip_address,
                userAgent: $model->user_agent,
            ),
            pageDetails: new PageDetails(
                pageType: PageType::tryFrom((string) ($model->page_type ?? '')),
                pageEntityId: $model->page_entity_id,
                pageUrl: $model->page_url,
            ),
            metadata: $model->metadata,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
