<?php

declare(strict_types=1);

namespace App\Features\Experience\App\DTOs;

use App\Features\Experience\Domain\Entities\TimelineItem;
use DateTimeImmutable;

/**
 * A single admin experience list row — the quick actions' response
 * shape. Built from the domain aggregate (the localized position and
 * company name resolve with the `en` fallback).
 */
final readonly class TimelineItemListItemDto
{
    public function __construct(
        public int $id,
        public string $position,
        public string $companyName,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public bool $isPublished,
        public bool $includeInResume,
    ) {
    }

    public static function fromEntity(TimelineItem $item, string $locale): self
    {
        $period = $item->toPeriod();

        return new self(
            id: $item->toId(),
            position: $item->positionFor($locale),
            companyName: $item->companyNameFor($locale),
            startedAt: $period->toStartedAt(),
            endedAt: $period->toEndedAt(),
            isPublished: $item->toIsPublished(),
            includeInResume: $item->toIncludeInResume(),
        );
    }
}
