<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\RepositoryInterfaces;

use App\Features\Blog\Domain\Entities\ArticleTag;

/**
 * The write side + admin/private reads of the article tags
 * (database.md:366-384, content-management.md:690-695): entity-based
 * CRUD over `article_tags` plus the slug-collision probe (ADR-0006) and the
 * tag↔skill name cross-check (the case-insensitive probe against
 * `skills` — content-management.md:695, ADR-0002 — invoked by the
 * store/update use cases, which surface the `name` validation error).
 * Tags are hard-deleted (no restore/force routes — the pivot rows
 * cascade).
 */
interface ArticleTagRepositoryInterface
{
    public function findById(int $id): ?ArticleTag;

    /**
     * Existing slug values matching the base prefix — the input of the
     * ADR-0006 numeric collision-suffix resolution (content-management.md:
     * 441-452).
     *
     * @return string[]
     */
    public function takenSlugs(string $base, ?int $ignoreId = null): array;

    /**
     * Whether ANY of the given tag names matches an existing skill
     * name (case-insensitive, any locale) — the article-tag side of
     * the tag↔skill cross-check (content-management.md:595,695). The
     * whole batch is probed in ONE query (no per-name round trips).
     *
     * @param  string[]  $names
     */
    public function anyTagNameMatchesSkill(array $names, ?int $ignoreId = null): bool;

    /**
     * Inserts or updates the tag (the translation rows are synced
     * batched); returns the persisted id.
     */
    public function save(ArticleTag $tag): int;

    /**
     * Hard-deletes the tag; the `article_tag_pivot` rows cascade.
     */
    public function delete(int $id): void;
}
