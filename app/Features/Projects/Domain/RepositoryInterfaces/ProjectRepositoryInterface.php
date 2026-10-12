<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\RepositoryInterfaces;

use App\Features\Projects\Domain\Entities\Project;
use App\Features\Projects\Domain\Exceptions\ProjectTagAlreadyAttachedException;

/**
 * The write side + admin/private reads of the Projects feature:
 * entity-based CRUD over `projects` with its aggregate parts
 * (translations, screenshots, engineering detail items, the
 * `project_tech_tags` pivot) and the quick actions of the admin list —
 * publication toggles (setting `published_at` once), featured state
 * with the max+1 default order, the atomic featured reorder, the
 * tech-writeup link and the tag attach/detach with the persisted
 * `tech_tags_count` maintained by the pivot model's Eloquent events.
 *
 * Slug support: `takenSlugs()` serves the collision-suffix resolution
 * (including trashed rows), `slugsOfProjects()`/`slugsOccupiedByActive()`
 * serve the restore uniqueness re-check in batch, and `currentSlugForOld()`
 * the public old-slug 301. The repository upserts `old_slugs` rows when
 * a slug changes and on force-delete. Deletes are instance-based so the
 * sitemap invalidation events fire; bulk operations run in batched
 * queries with a single invalidation; `forceDelete()` cleans the
 * project's RustFS files (preview image + screenshots).
 */
interface ProjectRepositoryInterface
{
    /**
     * The full aggregate with its translations, screenshots, detail
     * items and tech tags — non-trashed only.
     */
    public function findById(int $id): ?Project;

    /**
     * Like {@see findById()}, but also returns soft-deleted projects
     * (the trash view of the form uses it for restore).
     */
    public function findByIdIncludingTrashed(int $id): ?Project;

    /**
     * The full aggregate resolved by slug for the public detail page —
     * the caller decides the publication scope (the preview bypasses
     * `is_published`).
     */
    public function findBySlug(string $slug): ?Project;

    /**
     * The preview variant of {@see findBySlug()} — resolves the project
     * regardless of `is_published` (signed preview URLs).
     */
    public function findBySlugIncludingUnpublished(string $slug): ?Project;

    /**
     * The slugs of the given projects (soft-deleted included) — the
     * batch input of the restore uniqueness re-check.
     *
     * @param  int[]  $ids
     * @return array<int, string>  Keyed by project id.
     */
    public function slugsOfProjects(array $ids): array;

    /**
     * The subset of the given slugs occupied by NON-trashed projects —
     * soft-deleted entities occupy their slug, so the restore check is
     * against active rows only.
     *
     * @param  string[]  $slugs
     * @return string[]
     */
    public function slugsOccupiedByActive(array $slugs): array;

    /**
     * Existing slug values matching the base prefix (soft-deleted
     * included) — the input of the numeric collision-suffix resolution.
     *
     * @return string[]
     */
    public function takenSlugs(string $base, ?int $ignoreId = null): array;

    /**
     * The current slug mapped from an `old_slugs` record — null when
     * the slug is not recorded (a trashed project's history resolves to
     * nothing).
     */
    public function currentSlugForOld(string $oldSlug): ?string;

    /**
     * The RustFS asset paths of the given projects (the preview image
     * and every screenshot) — the input of the force-delete flows, in
     * which the USE CASE orchestrates the file deletion via the
     * asset cleaner (the repository only reports the paths).
     *
     * @param  int[]  $ids
     * @return array<int, string[]>  Keyed by project id.
     */
    public function assetPathsOf(array $ids): array;

    /**
     * Inserts or updates the aggregate in one transaction: the base
     * row, the translation sync (upsert by locale, active-locale
     * reconciliation), the screenshot reconcile (rows with IDs
     * updated, new rows created, absent rows deleted with their RustFS
     * files), the detail-item reconcile (same, no files) and the
     * tech-tag pivot sync (so the `tech_tags_count` stays consistent).
     * A slug change records the old slug in `old_slugs`. Returns the
     * persisted id so the caller can route to the created aggregate.
     */
    public function save(Project $project): int;

    public function delete(int $id): void;

    /**
     * @param  int[]  $ids
     */
    public function deleteMany(array $ids): void;

    public function restore(int $id): void;

    /**
     * @param  int[]  $ids
     */
    public function restoreMany(array $ids): void;

    /**
     * Permanently deletes the project — RustFS files are removed via
     * the model's `forceDelete()` override and an `old_slugs` record
     * preserves the slug history.
     */
    public function forceDelete(int $id): void;

    /**
     * @param  int[]  $ids
     */
    public function forceDeleteMany(array $ids): void;

    /**
     * Sets `is_published`; `published_at` is auto-set to `now()` on
     * the FIRST publish only (immutable thereafter). A single sitemap
     * invalidation covers the whole batch.
     *
     * @param  int[]  $ids
     */
    public function setPublicationMany(array $ids, bool $published): void;

    /**
     * Sets `is_featured`; when featuring, `featured_sort_order`
     * defaults to `max(featured_sort_order) + 1` if not already set;
     * unfeaturing preserves the value.
     */
    public function setFeatured(int $id, bool $featured): void;

    /**
     * Writes `featured_sort_order` as the zero-indexed position of the
     * submitted id array, atomically.
     *
     * @param  int[]  $ids  Featured project IDs in the new drag order.
     */
    public function reorderFeatured(array $ids): void;

    /**
     * Links or unlinks the tech-writeup article (`null` removes the
     * link). The published check happens in the request validation.
     */
    public function setTechWriteup(int $id, ?int $articleId): void;

    /**
     * Attaches a tech tag. The `tech_tags_count` increment happens via
     * the pivot model's `saved` event.
     *
     * @throws ProjectTagAlreadyAttachedException  When the tag is already attached (409).
     */
    public function attachTag(int $projectId, int $skillId): void;

    /**
     * Detaches a tech tag. The `tech_tags_count` decrement happens via
     * the pivot model's `deleted` event.
     */
    public function detachTag(int $projectId, int $skillId): void;
}
