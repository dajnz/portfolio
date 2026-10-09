<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\RepositoryInterfaces;

use App\Features\Blog\Domain\Entities\Article;
use App\Features\Blog\Domain\Exceptions\ArticleSkillAlreadyAttachedException;
use App\Features\Blog\Domain\Exceptions\RelatedArticleAlreadyLinkedException;

/**
 * The write side + admin/private reads of the Blog feature:
 * entity-based CRUD over `blog_articles` with its aggregate parts
 * (translations, the `article_tag_pivot` / `article_skill_pivot` rows,
 * the bidirectional `related_articles` pairs) and the quick actions of
 * the admin list — publication toggles (setting `published_at` once),
 * related-article attach/detach (both pair rows in one transaction,
 * 409 on duplicates) and skill-tag attach/detach (409 on duplicates).
 *
 * Slug support: `takenSlugs()` serves the collision-suffix resolution
 * (including trashed rows), `slugsOccupiedByActive()` serves the
 * restore uniqueness re-check in batch, `publishedIdsOf()` the use
 * cases' sitemap invalidation input and `currentSlugForOld()` the
 * public old-slug 301. The repository upserts `old_slugs` rows when a
 * slug changes and on force-delete. The `sitemap:xml` invalidation is
 * NOT a repository concern — the write use cases orchestrate it via
 * the shared invalidator. The translation sync is batched — the
 * per-row `saved` events are replaced by the equivalent `search_vector`
 * + `reading_time_minutes` rebuild.
 */
interface ArticleRepositoryInterface
{
    /**
     * The full aggregate with its translations — non-trashed only.
     */
    public function findById(int $id): ?Article;

    /**
     * Like {@see findById()}, but also returns soft-deleted articles
     * (the trash view of the form uses it for restore).
     */
    public function findByIdIncludingTrashed(int $id): ?Article;

    /**
     * The full aggregate resolved by slug — the caller decides the
     * publication scope (the preview bypasses `is_published`).
     */
    public function findBySlug(string $slug): ?Article;

    /**
     * The preview variant of {@see findBySlug()} — resolves the article
     * regardless of `is_published` (signed preview URLs).
     */
    public function findBySlugIncludingUnpublished(string $slug): ?Article;

    /**
     * Existing slug values matching the base prefix (soft-deleted
     * included) — the input of the numeric collision-suffix resolution.
     *
     * @return string[]
     */
    public function takenSlugs(string $base, ?int $ignoreId = null): array;

    /**
     * The slugs of the given articles (soft-deleted included) — the
     * batch input of the restore uniqueness re-check.
     *
     * @param  int[]  $ids
     * @return array<int, string>  Keyed by article id.
     */
    public function slugsOfArticles(array $ids): array;

    /**
     * The subset of the given slugs occupied by NON-trashed articles —
     * soft-deleted entities occupy their slug, so the restore check is
     * against active rows only.
     *
     * @param  string[]  $slugs
     * @return string[]
     */
    public function slugsOccupiedByActive(array $slugs): array;

    /**
     * The ids of the given articles that are PUBLISHED (soft-deleted
     * included) — the use cases' input for the conditional `sitemap:xml`
     * invalidation (the sitemap contains published articles only; the
     * invalidation DECISION lives in the app layer).
     *
     * @param  int[]  $ids
     * @return int[]
     */
    public function publishedIdsOf(array $ids): array;

    /**
     * The `related_articles` ids of the article — both directions of
     * the pairs, deduplicated. The input of the linkability re-check on
     * save: an id ALREADY linked is preserved even when its target got
     * unpublished.
     *
     * @return int[]
     */
    public function relatedIdsOf(int $articleId): array;

    /**
     * The current slug mapped from an `old_slugs` record — null when
     * the slug is not recorded (a trashed article's history resolves to
     * nothing).
     */
    public function currentSlugForOld(string $oldSlug): ?string;

    /**
     * The RustFS asset paths of the given articles (the lead image) —
     * the input of the force-delete flows, in which the USE CASE
     * orchestrates the file deletion via the asset cleaner (the
     * repository only reports the paths).
     *
     * @param  int[]  $ids
     * @return array<int, string[]>  Keyed by article id.
     */
    public function assetPathsOf(array $ids): array;

    /**
     * Inserts or updates the aggregate in one transaction: the base
     * row, the translation sync (batched replace with the
     * `search_vector` + `reading_time_minutes` rebuild), the
     * `article_tag_pivot` / `article_skill_pivot` syncs and the
     * `related_articles` reconcile (the bidirectional pairs of the
     * removed/added ids). A slug change records the old slug in
     * `old_slugs`.
     */
    public function save(Article $article): void;

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
     * Permanently deletes the article — the RustFS lead image is
     * removed via the shared asset cleaner orchestrated by the use
     * case and an `old_slugs` record preserves the slug history.
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
     * Creates the bidirectional relationship (both `(A, B)` and
     * `(B, A)` rows, atomically).
     *
     * @throws RelatedArticleAlreadyLinkedException  When the pair already exists (409).
     */
    public function attachRelated(int $articleId, int $relatedArticleId): void;

    /**
     * Removes both rows of the pair atomically.
     */
    public function detachRelated(int $articleId, int $relatedArticleId): void;

    /**
     * Attaches a skill tag via `article_skill_pivot`.
     *
     * @throws ArticleSkillAlreadyAttachedException  When the skill is already attached (409).
     */
    public function attachSkill(int $articleId, int $skillId): void;

    /**
     * Detaches a skill tag via `article_skill_pivot`.
     */
    public function detachSkill(int $articleId, int $skillId): void;
}
