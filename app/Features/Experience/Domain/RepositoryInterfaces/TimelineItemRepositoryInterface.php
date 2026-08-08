<?php

declare(strict_types=1);

namespace App\Features\Experience\Domain\RepositoryInterfaces;

use App\Features\Experience\Domain\Entities\TimelineItem;

/**
 * Write-side repository for the Experience feature (architecture.md:
 * 479-507): entity-based CRUD over `experience_timeline_items` with the
 * per-locale translation rows and the `experience_item_tech_tags` pivot
 * (saved as part of the aggregate — {@see TimelineItem::toTechTagIds()}).
 * Soft-delete lifecycle: `delete()` sets `deleted_at`, `restore()`
 * clears it, `forceDelete()` removes the row and its dependent rows.
 */
interface TimelineItemRepositoryInterface
{
    public function findById(int $id): ?TimelineItem;

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
     * active locales, preserving rows of disabled locales per
     * content-management.md:419) and the tech-tag pivot.
     */
    public function save(TimelineItem $item): void;
}
