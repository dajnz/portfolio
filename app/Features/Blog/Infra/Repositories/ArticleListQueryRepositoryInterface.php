<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Repositories;

use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Infra-level read contract for the Blog lists — the cursor-paginated
 * admin article list (with its filter bar and the JSON autocomplete
 * variant), the tag list and the picker option sources of the admin
 * forms. Separate from the domain
 * {@see \App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface}
 * because these reads return query rows, not entities — the same
 * split as the Projects/Experience list contracts.
 *
 * The contract stays on primitives and domain VOs: raw locale-resolved
 * rows (stdClass projections) and {@see TechSkillTag} entities — the
 * DTO conversion is the use cases' responsibility. The admin list
 * pagination itself lives behind the shared app-layer paginator
 * abstraction (the filters-only queries are supplied here).
 */
interface ArticleListQueryRepositoryInterface
{
    /**
     * The filtered admin article list query (admin-section-ui.md:
     * 387-403): locale-resolved rows (title with the `en` fallback,
     * the reading time and the `article_views` count) with the filter
     * bar applied — search over the per-locale title/content
     * (`?q=`), the article-tag AND-intersect (`?article_tags=`), the
     * skill-tag AND-intersect (`?skill_tags=` — separate params per
     * routes.md:204-205, ADR-0004), the published status (`?published=`),
     * the publication year (`?year=YYYY`) and the trashed view. The
     * cursor pagination (order, seek, count) is applied by the shared
     * paginator.
     *
     * @param  int[]  $articleTagIds
     * @param  int[]  $skillTagIds
     * @return QueryBuilder
     */
    public function adminListQuery(
        string $search,
        array $articleTagIds,
        array $skillTagIds,
        ?bool $published,
        ?int $year,
        bool $trashed,
    ): QueryBuilder;

    /**
     * The single admin list row of an article — the quick actions'
     * response shape (content-management.md:255) — or null when the
     * article does not exist.
     *
     * @return object|null
     */
    public function findAdminRow(int $id): ?object;

    /**
     * The article tags of the given articles via `article_tag_pivot` ↔
     * `article_tags`, locale-resolved name with the `en` fallback,
     * ordered by id.
     *
     * @param  int[]  $articleIds
     * @return array<int, array<int, object>>  Rows `{id, slug, name}` keyed by article id.
     */
    public function articleTagsFor(array $articleIds): array;

    /**
     * The skill tags of the given articles via `article_skill_pivot` ↔
     * `skills`, ordered for display (`skills.sort_order`, then id).
     *
     * @param  int[]  $articleIds
     * @return array<int, array<int, TechSkillTag>>  Keyed by article id.
     */
    public function skillTagsFor(array $articleIds): array;

    /**
     * The related-article counts of the given articles (both directions
     * of the `related_articles` pairs, deduplicated) — the static
     * Related column of the admin table (user request 2026-08-13).
     *
     * @param  int[]  $articleIds
     * @return array<int, int>  Keyed by article id.
     */
    public function relatedCountFor(array $articleIds): array;

    /**
     * The related articles of the given article as the edit form's
     * multi-select rows, locale-resolved titles with the `en` fallback,
     * ordered by id.
     *
     * @param  int[]  $articleIds
     * @return array<int, array<int, object>>  Rows `{id, title}` keyed by article id.
     */
    public function relatedRowsFor(array $articleIds): array;

    /**
     * The admin autocomplete JSON variant of `GET /admin/blog`
     * (admin-section-ui.md:539-572): ILIKE title search over ALL
     * locales, unpublished articles included, non-trashed only —
     * length-aware rows plus the total.
     *
     * @return array{rows: array<int, object>, total: int}
     */
    public function findForAdminAutocomplete(string $search, int $page, int $perPage): array;

    /**
     * The published, non-trashed articles as the Related Articles
     * picker options (ADR-0004-style), locale-resolved titles with the
     * `en` fallback, newest first — the current article excluded
     * (it cannot link to itself, content-management.md:687).
     *
     * @return array<int, object>  Rows `{id, slug, title}`.
     */
    public function findPublishedOptions(?int $excludeId, string $locale): array;

    /**
     * ALL article tags as the tag picker options of the blog list/form
     * pages (blog.yaml#BlogListPageProps.tags), locale-resolved names
     * with the `en` fallback, ordered by name.
     *
     * @return array<int, object>  Rows `{id, slug, name}`.
     */
    public function findAllTags(string $locale): array;

    /**
     * The filtered admin tag list query (admin-section-ui.md:315-320):
     * locale-resolved rows (name with the `en` fallback) with the
     * `article_tag_pivot` usage count and the cross-locale name
     * search (`?q=`). The cursor pagination is applied by the shared
     * paginator.
     *
     * @return QueryBuilder
     */
    public function tagsListQuery(string $search, string $locale): QueryBuilder;

    /**
     * The public blog index rows (step 8.2 — content-management.md:
     * 194-205, public-section-ui.md:209-238): published, non-trashed
     * articles ordered `published_at DESC, id DESC` with the
     * `?article_tags=` / `?skill_tags=` AND-intersects (ADR-0004) and the
     * `?year=` publication-year filter. The FTS `?q=` search
     * (`plainto_tsquery` + `ts_rank(..., 32)` via the shared
     * {@see \App\Features\Shared\Infra\Search\FtsQueryBuilder}) takes
     * over the ordering and selects the `ts_headline()` snippet
     * (the `<mark>`-wrapped search excerpt — database.md:676-685);
     * the keyset cursor seeks on `(rank, id)` / `(published_at, id)`.
     *
     * @param  int[]  $articleTagIds
     * @param  int[]  $skillTagIds
     * @return array<int, object>  Rows `{id, slug, title, short_description, published_at,
     *                             reading_time_minutes, lead_image}` + `fts_rank`/`fts_snippet` when searching.
     */
    public function findPublicPageRows(
        string $locale,
        int $perPage,
        ?PublicPageCursor $cursor,
        array $articleTagIds,
        array $skillTagIds,
        ?int $year,
        ?string $search,
    ): array;

    /**
     * The JSON dual-response variant of `GET /blog`
     * (routes.md:199-212): length-aware rows (page/per_page) with the
     * raw field values — no highlights (ADR-0007). The search uses the
     * PREFIX autocomplete form of the shared FtsQueryBuilder (the last
     * word becomes a prefix — public-section-ui.md:443, database.md:
     * 662-675).
     *
     * @param  int[]  $articleTagIds
     * @param  int[]  $skillTagIds
     * @return array{rows: array<int, object>, total: int}
     */
    public function findPublicJsonPage(
        string $locale,
        int $page,
        int $perPage,
        array $articleTagIds,
        array $skillTagIds,
        ?int $year,
        ?string $search,
    ): array;

    /**
     * The distinct publication years of the published, non-trashed
     * articles, newest first — the blog index `years` prop
     * (public-section-ui.md:216-219: the year filter is shown only
     * when more than one distinct year exists).
     *
     * @return int[]
     */
    public function yearsOfPublishedArticles(): array;

    /**
     * The `article_views` counter of an article — 0 when no row exists
     * yet (content-management.md:214-218; the public page shows the
     * pre-increment value).
     */
    public function viewsOf(int $articleId): int;

    /**
     * The published, non-trashed related articles of an article as the
     * public detail page's card rows (content-management.md:243 — an
     * article unpublished AFTER the link was created is hidden, same
     * rule as the tech-writeup link, :133), ordered `published_at DESC,
     * id DESC`.
     *
     * @return array<int, object>  Rows `{id, slug, title, short_description, published_at,
     *                             reading_time_minutes, lead_image}`.
     */
    public function findRelatedCards(int $articleId, string $locale): array;

    /**
     * The published, non-trashed articles of the RSS feed (seo.md:
     * 233-238) — the 20 most recent by `published_at`, with the
     * locale-resolved title/description and the lead image path.
     *
     * @return array<int, object>  Rows `{id, slug, title, short_description, published_at, lead_image}`.
     */
    public function findRssRows(string $locale, int $limit): array;
}
