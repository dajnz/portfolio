<?php

declare(strict_types=1);

namespace App\Features\Projects\App\Contracts;

use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * App-layer read contract for the Projects lists — the cursor-
 * paginated admin list (with its filter bar and the JSON autocomplete
 * variant) and the public infinite-scroll page (with tag intersect +
 * FTS search + date sort). Separate from the domain
 * {@see \App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface}
 * because these reads return query rows, not entities — the same
 * split as the Experience feature's list contract.
 *
 * The contract stays on primitives and domain VOs: raw locale-resolved
 * rows (stdClass projections) and {@see TechSkillTag} entities — the
 * DTO conversion is the use cases' responsibility. The admin list
 * pagination itself lives behind the shared app-layer paginator
 * abstraction (the filters-only query is supplied here; its framework
 * query builder is typed as `mixed` at this boundary).
 */
interface ProjectListQueryRepositoryInterface
{
    /**
     * The filtered admin list query: locale-resolved rows (title with
     * the `en` fallback) with the filter bar applied — title search
     * `?q=`, the featured toggle, the tech-tag AND-intersect `?tags=`,
     * the published status and the trashed view. The cursor pagination
     * (order, seek, count) is applied by the shared paginator.
     *
     * @return mixed  The filtered base query (framework builder).
     */
    public function adminListQuery(
        string $search,
        bool $featured,
        array $tags,
        ?bool $published,
        bool $trashed,
    ): mixed;

    /**
     * The single admin list row of a project — the quick actions'
     * response shape — or null when the project does not exist.
     *
     * @return object|null
     */
    public function findAdminRow(int $id): ?object;

    /**
     * The featured projects of the admin list's reorder section —
     * `is_featured` rows of the live view, ordered by
     * `featured_sort_order` then id (the same locale-resolved
     * projection as the list rows).
     *
     * @return object[]
     */
    public function findFeaturedRows(): array;

    /**
     * The public projects page: published (or all, in preview mode)
     * projects ordered by the date strategy with the
     * `?direction=asc|desc` variant (`published_at {dir}, id {dir}` —
     * `desc` default), with the optional tech-tag AND-intersect
     * (`?tags=`), the optional FTS `?q=` search (plainto_tsquery +
     * ts_rank, which takes over the ordering), and the opaque composite
     * keyset cursor `(sort_value, id)` as the domain
     * {@see PublicPageCursor} VO.
     *
     * The locale-resolved rows come from a single query with the
     * `en`-fallback join; the per-project tech tags and screenshot
     * paths are assembled on demand via {@see techTagsFor()} and
     * {@see screenshotsFor()} (the reconstruction loop of the public
     * use case needs rows only per batch).
     *
     * @return object[]
     */
    public function findPublicPageRows(
        string $locale,
        int $perPage,
        ?PublicPageCursor $cursor,
        string $sort,
        string $sortDirection,
        array $tagIds,
        ?string $search,
        bool $includeUnpublished,
    ): array;

    /**
     * Tech tags of the given projects via the pivot ↔ `skills` join,
     * ordered for display (`skills.sort_order`, then id).
     *
     * @param  int[]  $projectIds
     * @return array<int, array<int, TechSkillTag>>  Keyed by project ID.
     */
    public function techTagsFor(array $projectIds): array;

    /**
     * The gallery paths of the given projects, ordered for display.
     *
     * @param  int[]  $projectIds
     * @return array<int, array<int, array{image_path: string, alt_text: array<string, string>|null}>>
     *     Keyed by project ID.
     */
    public function screenshotsFor(array $projectIds): array;

    /**
     * The JSON dual-response variant of `GET /projects` — the
     * autocomplete/search: length-aware (page/per_page with a total
     * count) over the same locale-resolved projection, tag intersect
     * and FTS search, ordered by the date strategy with the direction
     * variant (`desc` default), or the FTS rank while searching.
     *
     * @return array{rows: array<int, object>, total: int}
     */
    public function findPublicJsonPage(
        string $locale,
        int $page,
        int $perPage,
        string $sort,
        string $sortDirection,
        array $tagIds,
        ?string $search,
    ): array;

    /**
     * The admin autocomplete JSON variant of `GET /admin/projects`:
     * ILIKE title search over ALL locales, unpublished projects
     * included, non-trashed only — length-aware rows plus the total.
     *
     * @return array{rows: array<int, object>, total: int}
     */
    public function findForAdminAutocomplete(string $search, int $page, int $perPage): array;
}
