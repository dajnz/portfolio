<?php

declare(strict_types=1);

namespace App\Features\Experience\App\DTOs;

use DateTimeImmutable;

/**
 * A single admin experience list row. The locale-resolved position and
 * company name resolve with the `en` fallback; the dates are converted at
 * the boundary. Immutable.
 */
final readonly class TimelineItemRowDto
{
    public function __construct(
        public int $id,
        public string $position,
        public string $companyName,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public bool $isPublished,
        public bool $includeInResume,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param  object  $row  The timeline item row with its translations loaded.
     */
    public static function fromRow(object $row, string $locale): self
    {
        $translations = [];

        foreach ($row->translations as $translation) {
            $translations[(string) $translation->locale] = $translation;
        }

        $translation = $translations[$locale] ?? $translations['en'] ?? null;

        return new self(
            id: (int) $row->id,
            position: (string) ($translation?->position ?? ''),
            companyName: (string) ($translation?->company_name ?? ''),
            startedAt: DateTimeImmutable::createFromInterface($row->started_at),
            endedAt: $row->ended_at === null ? null : DateTimeImmutable::createFromInterface($row->ended_at),
            isPublished: (bool) $row->is_published,
            includeInResume: (bool) $row->include_in_resume,
            createdAt: DateTimeImmutable::createFromInterface($row->created_at),
        );
    }
}
