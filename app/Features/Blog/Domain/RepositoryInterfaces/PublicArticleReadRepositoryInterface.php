<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\RepositoryInterfaces;

use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;

/**
 * Read-model contract of the PUBLIC blog surface (step 8.2): the
 * locale-resolved list/detail/feed reads served by the public
 * controllers. Lives in the DOMAIN layer because it is framework-free
 * — raw query rows (plain objects), domain entities and domain value
 * objects only, no Laravel types in the signatures (the B109/B11
 * read-model pattern — the same split as the shared
 * `ProjectReadRepositoryInterface`). The Eloquent implementation
 * lives in the Infra layer.
 *
 * The DTO conversion is the consuming use cases' responsibility.
 */
interface PublicArticleReadRepositoryInterface
{
    /**
     * The public blog index rows (content-management.md:194-205,
     * public-section-ui.md:209-238): published, non-trashed articles
     * ordered `published_at DESC, id DESC` with the `?article_tags=`
     * / `?skill_tags=` AND-intersects (B2) and the `?year=`
     * publication-year filter. The FTS `?q=` search (`plainto_tsquery`
     * + `ts_rank(..., 32)` via the shared
     * {@see \App\Features\Shared\Infra\Search\FtsQueryBuilder}) takes
     * over the ordering and selects the `ts_headline()` snippet (the
     * `<mark>`-wrapped search excerpt — database.md:676-685); the
     * keyset cursor seeks on `(rank, id)` / `(published_at, id)`.
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
     * raw field values — no highlights (B16). The search uses the
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
     * articles, newest first — the blog index `years` prop.
     *
     * @return int[]
     */
    public function yearsOfPublishedArticles(): array;

    /**
     * The `article_views` counter of an article — 0 when no row exists
     * yet (content-management.md:214-218).
     */
    public function viewsOf(int $articleId): int;

    /**
     * The published, non-trashed related articles of an article as the
     * public detail page's card rows (content-management.md:243 — an
     * article unpublished AFTER the link was created is hidden, :133),
     * ordered `published_at DESC, id DESC`.
     *
     * @return array<int, object>  Rows `{id, slug, title, short_description, published_at,
     *                             reading_time_minutes, lead_image}`.
     */
    public function findRelatedCards(int $articleId, string $locale): array;

    /**
     * The published, non-trashed articles of the RSS feed (seo.md:
     * 233-238), the most recent by `published_at`.
     *
     * @return array<int, object>  Rows `{id, slug, title, short_description, published_at, lead_image}`.
     */
    public function findRssRows(string $locale, int $limit): array;

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
     * ALL article tags as the blog index/filter options,
     * locale-resolved names with the `en` fallback, ordered by name.
     *
     * @return array<int, object>  Rows `{id, slug, name}`.
     */
    public function findAllTags(string $locale): array;

    /**
     * Whether at least one published, non-trashed project exists —
     * the public pages' header nav Projects-link condition (A10;
     * the v1.0.16 experience `has_projects` precedent). A
     * query-builder read in the Infra layer (the B11 cross-feature
     * pattern — the projects table is not part of the Blog feature).
     */
    public function hasPublishedProjects(): bool;
}
