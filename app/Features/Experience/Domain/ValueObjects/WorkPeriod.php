<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Value object: the employment period of a timeline item (check
 * `ended_at IS NULL OR ended_at > started_at` — an item must not end on or
 * before the day it started). A null `ended_at` marks an ongoing position
 * (`isCurrent()`) — only the chronologically last item may be ongoing
 * (enforced by the store/update use cases). Immutable; the date-order
 * invariant lives here, not in the entity. `toPrettyRange()` renders the
 * year-level period for frontend output (the timeline card's period line)
 * and `toDurationInYears()` the full years worked — projections the entity
 * re-exposes.
 */
final readonly class WorkPeriod
{
    public function __construct(
        private DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $endedAt,
    ) {
        if ($endedAt !== null && $endedAt <= $startedAt) {
            throw new InvalidArgumentException(
                "Work period ended_at must be after started_at ({$endedAt->format('Y-m-d')} <= {$startedAt->format('Y-m-d')})."
            );
        }
    }

    public function toStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function toEndedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function isCurrent(): bool
    {
        return $this->endedAt === null;
    }

    /**
     * "2020–2026" | "2020–Present" — the year-level period used on the
     * timeline cards and the admin list. The ongoing-position label is
     * passed in (e.g. `__('experience.present')`) — the domain stays
     * framework-free and never hardcodes a language.
     */
    public function toPrettyRange(string $presentLabel): string
    {
        return $this->startedAt->format('Y')
            .'–'
            .($this->endedAt?->format('Y') ?? $presentLabel);
    }

    /**
     * Full years worked (the diff's year component, floored): an ongoing
     * period is measured to today.
     */
    public function toDurationInYears(): int
    {
        $end = $this->endedAt ?? new DateTimeImmutable('now');

        return $this->startedAt->diff($end)->y;
    }
}
