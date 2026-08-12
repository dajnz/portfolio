<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Repositories;

use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Infra-level read contract for the Projects lists — the cursor-
 * paginated admin list (with its filter bar and the JSON autocomplete
 * variant) and the public infinite-scroll page (with tag intersect +
 * FTS search + date/complexity sorts). Separate from the domain
 * {@see \App\Features\Projects\Domain\RepositoryInterfaces\ProjectRepositoryInterface}
 * because these reads return query rows, not entities — the same
 * split as the Experience feature's list contract.
 *
 * The contract stays on primitives and domain VOs: raw locale-resolved
 * rows (stdClass projections) and {@see TechSkillTag} entities — the
 * DTO conversion is the use cases' responsibility. The admin list
 * pagination itself lives behind the shared app-layer paginator
 * abstraction (the filters-only query is supplied here).
 */
interface ProjectListQueryRepositoryInterface
{
    /**
     * The filtered admin list query (admin-section-ui.md:327-346):
     * locale-resolved rows (title with the `en` fallback) with the
     * filter bar applied — title search `?q=`, the featured toggle,
     * the tech-tag AND-intersect `?tags=`, the published status and
     * the trashed view. The cursor pagination (order, seek, count) is
     * applied by the shared paginator.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function adminListQuery(
        string $search,
        bool $featured,
        array $tags,
        ?bool $published,
        bool $trashed,
    ): QueryBuilder;

    /**
     * The single admin list row of a project — the quick actions'
     * response shape (B180, content-management.md:162) — or null when
     * the project does not exist.
     *
     * @return object|null
     */
    public function findAdminRow(int $id): ?object;

    /**
     * The featured projects of the admin list's reorder section
     * (admin-section-ui.md:338) — `is_featured` rows of the live view,
     * ordered by `featured_sort_order` then id (the same locale-
     * resolved projection as the list rows).
     *
     * @return object[]
     */
    public function findFeaturedRows(): array;

    /**
     * The public projects page (content-management.md:125-129,
     * public-section-ui.md:346-391): published (or all, in preview
     * mode) projects ordered by the given sort strategy — `date`
     * (`published_at DESC, id DESC`, the default) or `complexity`
     * (`tech_tags_count DESC, id DESC`) — with the optional tech-tag
     * AND-intersect (`?tags=`), the optional FTS `?q=` search
     * (plainto_tsquery + ts_rank, which takes over the ordering per
     * database.md:651-660), and the opaque composite keyset cursor
     * `(sort_value, id)` as the domain {@see PublicPageCursor} VO.
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
        array $tagIds,
        ?string $search,
        bool $includeUnpublished,
    ): array;

    /**
     * Tech tags of the given projects via the pivot ↔ `skills` join,
     * ordered for display (`skills.sort_order`, then id —
     * content-management.md:79).
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
     * The JSON dual-response variant of `GET /projects` (routes.md:
     * 199-212) — the autocomplete/search: length-aware
     * (page/per_page with a total count) over the same locale-resolved
     * projection, tag intersect and FTS search, ordered by the given
     * sort (`date`/`complexity`, or the FTS rank while searching).
     *
     * @return array{rows: array<int, object>, total: int}
     */
    public function findPublicJsonPage(
        string $locale,
        int $page,
        int $perPage,
        string $sort,
        array $tagIds,
        ?string $search,
    ): array;

    /**
     * The admin autocomplete JSON variant of `GET /admin/projects`
     * (admin-section-ui.md:539-572): ILIKE title search over ALL
     * locales, unpublished projects included, non-trashed only —
     * length-aware rows plus the total.
     *
     * @return array{rows: array<int, object>, total: int}
     */
    public function findForAdminAutocomplete(string $search, int $page, int $perPage): array;
}
