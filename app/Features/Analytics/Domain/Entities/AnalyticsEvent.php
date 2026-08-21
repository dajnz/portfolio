<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Entities;

use App\Features\Analytics\Domain\ValueObjects\ClientIds;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use App\Features\Shared\Domain\BaseEntity;
use DateTimeImmutable;

/**
 * Base class for the typed analytics events (database.md:486-505,
 * analytics.md:11-41, B289): the shared storage fields — the client
 * identifiers (`ClientIds`: session id, anonymized IP hash, User-Agent
 * — validated by the VO) and the page context (`PageDetails`: page
 * type, entity id, page URL — validated by the VO) — plus the
 * event-type identity. Every concrete event type knows its own
 * semantic payload — the opaque `metadata` blob is modelled as
 * concrete properties on the subclasses, which validate their own
 * invariants and serialize back to the stored `metadata` jsonb via
 * `toEventSpecificData()`.
 *
 * `eventType()` returns the stored `event_type` value — a subclass
 * declares its own via the `EVENT_TYPE` constant (overridden where the
 * type is dynamic, e.g. the CTA clicks).
 */
abstract class AnalyticsEvent extends BaseEntity
{
    public function __construct(
        private readonly int $id,
        private readonly ClientIds $clientIds,
        private readonly PageDetails $pageDetails,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function eventType(): string
    {
        return static::EVENT_TYPE;
    }

    public function toClientIds(): ClientIds
    {
        return $this->clientIds;
    }

    public function toPageDetails(): PageDetails
    {
        return $this->pageDetails;
    }

    public function toSessionId(): string
    {
        return $this->clientIds->toSessionId();
    }

    public function toIpAddress(): ?string
    {
        return $this->clientIds->toIpAddress();
    }

    public function toUserAgent(): ?string
    {
        return $this->clientIds->toUserAgent();
    }

    public function toPageUrl(): ?string
    {
        return $this->pageDetails->toPageUrl();
    }

    public function toCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** The event's own payload as stored in the `metadata` jsonb column. */
    abstract public function toEventSpecificData(): array;
}
