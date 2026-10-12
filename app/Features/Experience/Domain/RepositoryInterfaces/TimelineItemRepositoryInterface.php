<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\RepositoryInterfaces;

use App\Features\Experience\Domain\Entities\TimelineItem;
use DateTimeImmutable;

/**
 * Write-side repository for the Experience feature: entity-based CRUD over
 * `experience_timeline_items` with the per-locale translation rows and the
 * `experience_item_tech_tags` pivot (saved as part of the aggregate).
 * Soft-delete lifecycle: `delete()` sets `deleted_at`, `restore()` clears
 * it, `forceDelete()` removes the row and its dependent rows.
 */
interface TimelineItemRepositoryInterface
{
    public function findById(int $id): ?TimelineItem;

    /**
     * The chronologically preceding item (greatest `started_at` strictly
     * below `$startedAt`, non-trashed) — the continuity anchor.
     * `$ignoreId` excludes the item being stored/updated itself.
     */
    public function predecessorFor(DateTimeImmutable $startedAt, ?int $ignoreId = null): ?TimelineItem;

    /**
     * The chronologically following item (smallest `started_at` strictly
     * above `$startedAt`, non-trashed) — the continuity anchor.
     * `$ignoreId` excludes the item being stored/updated itself.
     */
    public function successorFor(DateTimeImmutable $startedAt, ?int $ignoreId = null): ?TimelineItem;

    /**
     * Closes an ongoing item's period (adding a newer item auto-fills its
     * predecessor's `ended_at` with the new item's `started_at`). An
     * instance update so the model's `saved` event (sitemap invalidation)
     * fires for published items.
     */
    public function setEndedAt(int $id, DateTimeImmutable $endedAt): void;

    /**
     * Flips `is_published` — an instance update so the model's `saved`
     * event (sitemap invalidation) fires on both publish and unpublish.
     */
    public function setPublished(int $id, bool $published): void;

    /**
     * Soft-deletes the item (its translations and pivot rows stay).
     */
    public function delete(int $id): void;

    /**
     * Clears `deleted_at`; a no-op for non-trashed rows.
     */
    public function restore(int $id): void;

    /**
     * Permanently removes the item — translations cascade, the pivot
     * rows are removed explicitly.
     */
    public function forceDelete(int $id): void;

    /**
     * Inserts or updates the item, syncing its translations (upsert for
     * active locales, preserving rows of disabled locales) and the
     * tech-tag pivot. Returns the persisted id so the caller can route
     * to the created item.
     */
    public function save(TimelineItem $item): int;
}
