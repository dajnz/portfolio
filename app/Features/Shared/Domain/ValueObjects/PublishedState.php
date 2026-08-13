<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\ValueObjects;

use DateTimeImmutable;

/**
 * Value object: a content entity's publication state (projects,
 * blog articles — database.md:210, content-management.md:251) — the
 * `is_published` flag and the immutable `published_at` (auto-set on
 * the FIRST publish only). `publish()` keeps an already-set timestamp
 * (the first-publish rule), `unpublish()` preserves it for a possible
 * re-publish. Shared by the Projects and Blog features. Immutable.
 */
final readonly class PublishedState
{
    public function __construct(
        private bool $isPublished,
        private ?DateTimeImmutable $publishedAt = null,
    ) {
    }

    public static function unpublished(): self
    {
        return new self(isPublished: false);
    }

    public static function published(?DateTimeImmutable $publishedAt = null): self
    {
        return new self(isPublished: true, publishedAt: $publishedAt);
    }

    public function toIsPublished(): bool
    {
        return $this->isPublished;
    }

    public function toPublishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }

    /**
     * Marks the state as published; `published_at` is set only when
     * still absent — the value is immutable after the first publish.
     */
    public function publish(DateTimeImmutable $now): self
    {
        return new self(isPublished: true, publishedAt: $this->publishedAt ?? $now);
    }

    /**
     * Marks the state as unpublished, preserving `published_at`.
     */
    public function unpublish(): self
    {
        return new self(isPublished: false, publishedAt: $this->publishedAt);
    }
}
