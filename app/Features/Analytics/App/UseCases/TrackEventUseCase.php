<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\Domain\Entities\AnalyticsEvent;
use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Domain\Factories\AnalyticsEventFactory;
use App\Features\Analytics\Domain\RepositoryInterfaces\AnalyticsEventRepositoryInterface;
use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use App\Features\Shared\App\Contracts\AnalyticsConfigInterface;
use App\Features\Shared\App\Contracts\LoggerInterface;
use DateTimeImmutable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use TypeError;

/**
 * Ingests one validated batch of analytics events. Batches from known
 * crawlers or without a User-Agent are silently discarded — nothing is
 * stored, the endpoint still answers 204. Each event is parsed into its
 * typed entity (the client identifiers and the page context are built
 * as their VOs); an event the domain cannot materialize (unknown event
 * type or malformed payload) rejects the entire batch with a 422
 * (everything stored is typed and parseable). The client IP is stored
 * only as a keyed HMAC-SHA256 digest; the User-Agent is kept as-is for
 * browser breakdowns.
 */
final class TrackEventUseCase
{
    public function __construct(
        private readonly AnalyticsEventRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
        private readonly AnalyticsConfigInterface $analyticsConfig,
    ) {
    }

    /**
     * @param list<array{
     *     event_type: string,
     *     session_id: string,
     *     page_type?: ?string,
     *     page_entity_id?: ?int,
     *     page_url?: ?string,
     *     metadata?: ?array,
     * }> $events
     */
    public function track(array $events, string $ip, ?string $userAgent): void
    {
        if ($this->isBot($userAgent)) {
            return;
        }

        $startedAt = hrtime(true);
        $ipHash = hash_hmac(
            'sha256',
            $ip,
            $this->analyticsConfig->ipHashKey(),
        );
        $createdAt = new DateTimeImmutable();

        $entities = [];

        foreach ($events as $event) {
            $entity = $this->parseEvent($event, $ipHash, $userAgent, $createdAt);

            if ($entity === null) {
                throw ValidationException::withMessages([
                    'batch' => [__('validation.analytics.event_invalid')],
                ]);
            }

            $entities[] = $entity;
        }

        $this->repository->insertBatch($entities);

        $this->logger->channel('analytics')->info('Analytics batch ingested', [
            'batch_size' => count($entities),
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ]);
    }

    /**
     * @param array{
     *     event_type: string,
     *     session_id: string,
     *     page_type?: ?string,
     *     page_entity_id?: ?int,
     *     page_url?: ?string,
     *     metadata?: ?array,
     * } $event
     */
    private function parseEvent(array $event, string $ipHash, ?string $userAgent, DateTimeImmutable $createdAt): ?AnalyticsEvent
    {
        try {
            return AnalyticsEventFactory::fromRow(
                id: 0,
                eventType: $event['event_type'],
                clientIds: new ClientIds(
                    sessionId: $event['session_id'],
                    ipAddress: $ipHash,
                    userAgent: $userAgent,
                ),
                pageDetails: new PageDetails(
                    pageType: PageType::tryFrom((string) ($event['page_type'] ?? '')),
                    pageEntityId: $event['page_entity_id'] ?? null,
                    pageUrl: $event['page_url'] ?? null,
                ),
                metadata: $event['metadata'] ?? null,
                createdAt: $createdAt,
            );
        } catch (InvalidArgumentException | TypeError) {
            return null;
        }
    }

    private function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '') {
            return true;
        }

        return preg_match(
            $this->analyticsConfig->botUserAgentPattern(),
            $userAgent,
        ) === 1;
    }
}
