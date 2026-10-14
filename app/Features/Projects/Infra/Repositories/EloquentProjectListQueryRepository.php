<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Repositories;

use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\PublicPageCursor;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of {@see ProjectListQueryRepositoryInterface}.
 *
 * The public page query is a single locale-resolved projection
 * (`coalesce(pl.*, pe.*)` with the `en` fallback) over `projects`,
 * extended with the tech-tag AND-intersect, the FTS search
 * (`plainto_tsquery` + `ts_rank(..., 32)` normalization; when present
 * it takes over the ordering) and the keyset cursor of the sort
 * strategy (date / rank, each with the mandatory `id` tiebreaker).
 * Tech tags and screenshot paths are assembled per page from two
 * supplemental queries.
 *
 * The admin list rows use the same locale-resolved query-builder
 * projection (raw rows — never Eloquent models), so the consuming use
 * cases map plain data; the shared paginator applies the ordering, the
 * cursor seek and the count.
 */
final class EloquentProjectListQueryRepository implements ProjectListQueryRepositoryInterface
{
    public function adminListQuery(
        string $search,
        bool $featured,
        array $tags,
        ?bool $published,
        bool $trashed,
    ): QueryBuilder {
        $query = $this->adminRowsQuery()
            ->when(! $trashed, fn (QueryBuilder $q): QueryBuilder => $q->whereNull('p.deleted_at'))
            ->when($trashed, fn (QueryBuilder $q): QueryBuilder => $q->whereNotNull('p.deleted_at'))
            ->when($search !== '', fn (QueryBuilder $q): QueryBuilder => $this->applyTitleSearch($q, $search))
            ->when($featured, fn (QueryBuilder $q): QueryBuilder => $q->where('p.is_featured', true))
            ->when($tags !== [], fn (QueryBuilder $q): QueryBuilder => $this->applyTagIntersect($q, $tags))
            ->when($published !== null, fn (QueryBuilder $q): QueryBuilder => $q->where('p.is_published', $published));

        return $query;
    }

    public function findAdminRow(int $id): ?object
    {
        $row = $this->adminRowsQuery()
            ->where('p.id', $id)
            ->first();

        return $row ?? null;
    }

    public function findFeaturedRows(): array
    {
        return $this->adminRowsQuery()
            ->whereNull('p.deleted_at')
            ->where('p.is_featured', true)
            ->orderBy('p.featured_sort_order')
            ->orderBy('p.id')
            ->get()
            ->all();
    }

    public function findPublicPageRows(
        string $locale,
        int $perPage,
        ?PublicPageCursor $cursor,
        string $sort,
        string $sortDirection,
        array $tagIds,
        ?string $search,
        bool $includeUnpublished,
    ): array {
        $query = $this->publicRowsQuery($locale)
            ->when(! $includeUnpublished, fn (QueryBuilder $q): QueryBuilder => $q->where('p.is_published', true))
            ->when($tagIds !== [], fn (QueryBuilder $q): QueryBuilder => $this->applyTagIntersect($q, $tagIds));

        $searching = is_string($search) && $search !== '';
        if ($searching) {
            $query = $this->applyFtsSearch($query, (string) $search);
        }

        if ($cursor !== null) {
            $query = $this->applyCursor($query, $cursor, $sort, $sortDirection, $searching, $search);
        }

        return $query
            ->orderBy($this->orderColumn($sort, $searching), $sortDirection)
            ->orderBy('p.id', $sortDirection)
            ->limit($perPage)
            ->get()
            ->all();
    }

    public function techTagsFor(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $tagsByProject = [];
        foreach (DB::table('project_tech_tags as pt')
            ->join('skills as s', 's.id', '=', 'pt.skill_id')
            ->whereIn('pt.project_id', $projectIds)
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->select(['pt.project_id', 's.id', 's.name', 's.icon'])
            ->get() as $row) {
            $tagsByProject[(int) $row->project_id][] = new TechSkillTag(
                id: (int) $row->id,
                skillName: $row->name,
                icon: $row->icon === null ? null : new SkillIcon($row->icon),
            );
        }

        return $tagsByProject;
    }

    public function screenshotsFor(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $shotsByProject = [];
        foreach (DB::table('project_screenshots')
            ->whereIn('project_id', $projectIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->select(['project_id', 'image_path', 'alt_text'])
            ->get() as $row) {
            $shotsByProject[(int) $row->project_id][] = [
                'image_path' => $row->image_path,
                'alt_text' => $this->decodeJsonMap($row->alt_text),
            ];
        }

        return $shotsByProject;
    }

    public function findPublicJsonPage(
        string $locale,
        int $page,
        int $perPage,
        string $sort,
        string $sortDirection,
        array $tagIds,
        ?string $search,
    ): array {
        $query = $this->publicRowsQuery($locale)
            ->where('p.is_published', true)
            ->when($tagIds !== [], fn (QueryBuilder $q): QueryBuilder => $this->applyTagIntersect($q, $tagIds));

        $searching = is_string($search) && $search !== '';
        if ($searching) {
            $query = $this->applyFtsSearch($query, $search);
        }

        $total = $query->count();

        $rows = (clone $query)
            ->orderBy($this->orderColumn($sort, $searching), $sortDirection)
            ->orderBy('p.id', $sortDirection)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public function findForAdminAutocomplete(string $search, int $page, int $perPage): array
    {
        $query = $this->adminRowsQuery()
            ->when($search !== '', fn (QueryBuilder $q): QueryBuilder => $this->applyTitleSearch($q, $search));

        $total = $query->count();

        $rows = (clone $query)
            ->orderByDesc('p.updated_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * The admin list projection — the locale-resolved title (the `en`
     * fallback) plus every row field the admin surfaces render.
     */
    private function adminRowsQuery(): QueryBuilder
    {
        $locale = App::getLocale();

        return DB::table('projects as p')
            ->leftJoin('project_translations as pl', function ($join) use ($locale): void {
                $join->on('pl.project_id', '=', 'p.id')
                    ->where('pl.locale', '=', $locale);
            })
            ->leftJoin('project_translations as pe', function ($join): void {
                $join->on('pe.project_id', '=', 'p.id')
                    ->where('pe.locale', '=', 'en');
            })
            ->select([
                'p.id',
                'p.slug',
                DB::raw('coalesce(pl.title, pe.title, \'\') as title'),
                'p.published_at',
                'p.preview_image',
                'p.preview_image_alt',
                'p.github_url',
                'p.live_demo_url',
                'p.is_featured',
                'p.is_published',
                'p.featured_sort_order',
                'p.tech_tags_count',
                'p.created_at',
                'p.updated_at',
            ]);
    }

    /**
     * The locale-resolved public projection with the `en` fallback —
     * shared by the plain, searched and keyset variants.
     */
    private function publicRowsQuery(string $locale): QueryBuilder
    {
        return DB::table('projects as p')
            ->leftJoin('project_translations as pl', function ($join) use ($locale): void {
                $join->on('pl.project_id', '=', 'p.id')
                    ->where('pl.locale', '=', $locale);
            })
            ->leftJoin('project_translations as pe', function ($join): void {
                $join->on('pe.project_id', '=', 'p.id')
                    ->where('pe.locale', '=', 'en');
            })
            ->select([
                'p.id',
                'p.slug',
                DB::raw('coalesce(pl.title, pe.title, \'\') as title'),
                DB::raw('coalesce(pl.brief_description, pe.brief_description, \'\') as brief_description'),
                'p.published_at',
                'p.preview_image',
                'p.preview_image_alt',
                'p.github_url',
                'p.live_demo_url',
                'p.is_featured',
                'p.tech_tags_count',
                'p.created_at',
                'p.updated_at',
            ])
            ->whereNull('p.deleted_at');
    }

    /**
     * The admin title search over ALL per-locale translation rows.
     */
    private function applyTitleSearch(QueryBuilder $query, string $search): QueryBuilder
    {
        return $query->whereIn('p.id', function ($sub) use ($search): void {
            $sub->select('project_id')
                ->from('project_translations')
                ->where('title', 'ilike', "%{$search}%");
        });
    }

    /**
     * The `?tags=` AND/intersection filter: projects whose tech-tag set
     * contains ALL of the submitted skill IDs.
     *
     * @param  int[]  $tagIds
     */
    private function applyTagIntersect(QueryBuilder $query, array $tagIds): QueryBuilder
    {
        return $query->whereIn('p.id', function ($sub) use ($tagIds): void {
            $sub->select('project_id')
                ->from('project_tech_tags')
                ->whereIn('skill_id', $tagIds)
                ->groupBy('project_id')
                ->havingRaw('count(distinct skill_id) = ?', [count($tagIds)]);
        });
    }

    /**
     * FTS search: `search_vector @@ plainto_tsquery(dictionary, :q)`
     * over the locale-resolved vector (the `en` fallback vector when the
     * locale row is missing), ordered by `ts_rank(..., 32)` — the rank
     * column is selected so the keyset predicate and the ORDER BY can
     * reference it.
     */
    private function applyFtsSearch(QueryBuilder $query, string $search): QueryBuilder
    {
        $dictionary = $this->dictionaryFor(App::getLocale());
        $queryExpression = 'plainto_tsquery(?, ?)';

        $query->whereRaw(
            'coalesce(pl.search_vector, pe.search_vector) @@ '.$queryExpression,
            [$dictionary, $search]
        );

        return $query->selectRaw(
            'ts_rank(coalesce(pl.search_vector, pe.search_vector), '.$queryExpression.', 32) as fts_rank',
            [$dictionary, $search],
        );
    }

    /**
     * The keyset predicate of the active order: the `date` strategy
     * (`(published_at, id)`) or the FTS rank (`(fts_rank, id)` when
     * searching) — each with the mandatory `id` tiebreaker and the
     * strict seek. The seek operators mirror the sort direction: `<`/
     * `id <` in `desc` order, `>`/`id >` in `asc` order. Split into the
     * boundary and tie disjuncts for readability.
     */
    private function applyCursor(
        QueryBuilder $query,
        PublicPageCursor $cursor,
        string $sort,
        string $sortDirection,
        bool $searching,
        ?string $search,
    ): QueryBuilder {
        $ascending = $sortDirection === 'asc';

        return $query->where(function (QueryBuilder $keyset) use ($cursor, $sort, $ascending, $searching, $search): void {
            $keyset->where(function (QueryBuilder $boundary) use ($cursor, $sort, $ascending, $searching, $search): void {
                $this->applyStrictSeek($boundary, $cursor, $sort, $ascending, $searching, $search);
            });

            $keyset->orWhere(function (QueryBuilder $tie) use ($cursor, $sort, $ascending, $searching, $search): void {
                $this->applyTieSeek($tie, $cursor, $sort, $ascending, $searching, $search);
            });
        });
    }

    /**
     * The strict boundary: rows sorting strictly after the cursor
     * position — `<` on the sort value in `desc` order, `>` in `asc`.
     */
    private function applyStrictSeek(
        QueryBuilder $query,
        PublicPageCursor $cursor,
        string $sort,
        bool $ascending,
        bool $searching,
        ?string $search,
    ): void {
        $operator = $ascending ? '>' : '<';

        if ($searching) {
            $query->whereRaw($this->rankExpression($search).' '.$operator.' ?', [
                ...$this->rankBindings($search),
                $cursor->toSortValue(),
            ]);

            return;
        }

        $query->where($this->seekColumn($sort, $searching), $operator, $this->seekValue($cursor, $sort));
    }

    /**
     * The tie disjunct: an equal sort value with an id sorting after
     * the cursor's — `id <` in `desc` order, `id >` in `asc` — the
     * deterministic tiebreaker.
     */
    private function applyTieSeek(
        QueryBuilder $query,
        PublicPageCursor $cursor,
        string $sort,
        bool $ascending,
        bool $searching,
        ?string $search,
    ): void {
        if ($searching) {
            $query->whereRaw($this->rankExpression($search).' = ?', [
                ...$this->rankBindings($search),
                $cursor->toSortValue(),
            ]);
        } else {
            $query->where($this->seekColumn($sort, $searching), '=', $this->seekValue($cursor, $sort));
        }

        $query->where('p.id', $ascending ? '>' : '<', $cursor->toId());
    }

    /**
     * The rank expression of the FTS seek — repeated verbatim in the
     * WHERE/ORDER BY so the planner sees one expression.
     */
    private function rankExpression(string $search): string
    {
        return 'ts_rank(coalesce(pl.search_vector, pe.search_vector), plainto_tsquery(?, ?), 32)';
    }

    /**
     * The rank expression's bound values (dictionary, query).
     *
     * @return array{0: string, 1: string}
     */
    private function rankBindings(string $search): array
    {
        return [$this->dictionaryFor(App::getLocale()), $search];
    }

    /**
     * The sort column of the plain strategies — `date` sorts by the
     * published timestamp.
     */
    private function seekColumn(string $sort, bool $searching): string
    {
        if ($searching) {
            return 'fts_rank';
        }

        return 'p.published_at';
    }

    /**
     * The cursor's sort value cast for the active strategy.
     */
    private function seekValue(PublicPageCursor $cursor, string $sort): string|int
    {
        return $cursor->toSortValue();
    }

    private function orderColumn(string $sort, bool $searching): string
    {
        if ($searching) {
            return 'fts_rank';
        }

        return 'p.published_at';
    }

    private function dictionaryFor(string $locale): string
    {
        $locales = Config::get('portfolio.fts.locales', []);

        return is_array($locales) && isset($locales[$locale])
            ? (string) $locales[$locale]
            : 'english';
    }

    /**
     * @return array<string, string>|null
     */
    private function decodeJsonMap(mixed $value): ?array
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }
}
