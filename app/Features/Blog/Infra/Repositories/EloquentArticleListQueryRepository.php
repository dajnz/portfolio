<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Repositories;

use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent implementation of {@see ArticleListQueryRepositoryInterface}.
 *
 * The admin list projection is a single locale-resolved query
 * (`coalesce(pl.*, pe.*)` with the `en` fallback — content-management.md:
 * 415) over `blog_articles`, extended with the `article_views` count,
 * the separate article-tag / skill-tag AND-intersects
 * (`?article_tags=` / `?skill_tags=` — routes.md:204-205, B2), the
 * title/content search, the published status and the publication-year
 * filter. Tag chips, skill tags and related rows are assembled per
 * page from supplemental queries. The admin list pagination itself
 * lives behind the shared app-layer paginator (the filters-only query
 * is supplied here).
 *
 * The tag list rows use the same locale-resolved projection over
 * `article_tags` with the `article_tag_pivot` usage count.
 */
final class EloquentArticleListQueryRepository implements ArticleListQueryRepositoryInterface
{
    public function adminListQuery(
        string $search,
        array $articleTagIds,
        array $skillTagIds,
        ?bool $published,
        ?int $year,
        bool $trashed,
    ): QueryBuilder {
        $query = $this->adminRowsQuery()
            ->when(! $trashed, fn (QueryBuilder $q): QueryBuilder => $q->whereNull('a.deleted_at'))
            ->when($trashed, fn (QueryBuilder $q): QueryBuilder => $q->whereNotNull('a.deleted_at'))
            ->when($search !== '', fn (QueryBuilder $q): QueryBuilder => $this->applyTextSearch($q, $search))
            ->when($articleTagIds !== [], fn (QueryBuilder $q): QueryBuilder => $this->applyArticleTagIntersect($q, $articleTagIds))
            ->when($skillTagIds !== [], fn (QueryBuilder $q): QueryBuilder => $this->applySkillTagIntersect($q, $skillTagIds))
            ->when($published !== null, fn (QueryBuilder $q): QueryBuilder => $q->where('a.is_published', $published))
            ->when($year !== null, fn (QueryBuilder $q): QueryBuilder => $q->whereRaw('extract(year from a.published_at) = ?', [$year]));

        return $query;
    }

    public function findAdminRow(int $id): ?object
    {
        $row = $this->adminRowsQuery()
            ->where('a.id', $id)
            ->first();

        return $row ?? null;
    }

    public function articleTagsFor(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        $locale = app()->getLocale();

        $tagsByArticle = [];
        foreach (DB::table('article_tag_pivot as atp')
            ->join('article_tags as at', 'at.id', '=', 'atp.tag_id')
            ->leftJoin('article_tag_translations as atl', function ($join) use ($locale): void {
                $join->on('atl.article_tag_id', '=', 'at.id')
                    ->where('atl.locale', '=', $locale);
            })
            ->leftJoin('article_tag_translations as ate', function ($join): void {
                $join->on('ate.article_tag_id', '=', 'at.id')
                    ->where('ate.locale', '=', 'en');
            })
            ->whereIn('atp.article_id', $articleIds)
            ->orderBy('at.id')
            ->select([
                'atp.article_id',
                'at.id',
                'at.slug',
                DB::raw('coalesce(atl.name, ate.name, \'\') as name'),
            ])
            ->get() as $row) {
            $tagsByArticle[(int) $row->article_id][] = $row;
        }

        return $tagsByArticle;
    }

    public function skillTagsFor(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        $tagsByArticle = [];
        foreach (DB::table('article_skill_pivot as asp')
            ->join('skills as s', 's.id', '=', 'asp.skill_id')
            ->whereIn('asp.article_id', $articleIds)
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->select(['asp.article_id', 's.id', 's.name', 's.icon'])
            ->get() as $row) {
            $tagsByArticle[(int) $row->article_id][] = new TechSkillTag(
                id: (int) $row->id,
                skillName: $row->name,
                icon: $row->icon === null ? null : new SkillIcon($row->icon),
            );
        }

        return $tagsByArticle;
    }

    public function relatedFor(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        $locale = app()->getLocale();

        $relatedByArticle = [];
        foreach (DB::table('related_articles as ra')
            ->join('blog_articles as a', 'a.id', '=', 'ra.related_article_id')
            ->leftJoin('blog_article_translations as rl', function ($join) use ($locale): void {
                $join->on('rl.article_id', '=', 'a.id')
                    ->where('rl.locale', '=', $locale);
            })
            ->leftJoin('blog_article_translations as re', function ($join): void {
                $join->on('re.article_id', '=', 'a.id')
                    ->where('re.locale', '=', 'en');
            })
            ->whereIn('ra.article_id', $articleIds)
            ->orderBy('a.id')
            ->select([
                'ra.article_id',
                'a.id',
                DB::raw('coalesce(rl.title, re.title, \'\') as title'),
            ])
            ->get() as $row) {
            $relatedByArticle[(int) $row->article_id][] = $row;
        }

        return $relatedByArticle;
    }

    public function findForAdminAutocomplete(string $search, int $page, int $perPage): array
    {
        $query = $this->adminRowsQuery()
            ->when($search !== '', fn (QueryBuilder $q): QueryBuilder => $this->applyTextSearch($q, $search));

        $total = $query->count();

        $rows = (clone $query)
            ->orderByDesc('a.updated_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public function findPublishedOptions(?int $excludeId, string $locale): array
    {
        return DB::table('blog_articles as a')
            ->leftJoin('blog_article_translations as al', function ($join) use ($locale): void {
                $join->on('al.article_id', '=', 'a.id')
                    ->where('al.locale', '=', $locale);
            })
            ->leftJoin('blog_article_translations as ae', function ($join): void {
                $join->on('ae.article_id', '=', 'a.id')
                    ->where('ae.locale', '=', 'en');
            })
            ->whereNull('a.deleted_at')
            ->where('a.is_published', true)
            ->when($excludeId !== null, fn (QueryBuilder $q): QueryBuilder => $q->where('a.id', '!=', $excludeId))
            ->orderByDesc('a.published_at')
            ->select([
                'a.id',
                'a.slug',
                DB::raw('coalesce(al.title, ae.title, \'\') as title'),
            ])
            ->get()
            ->all();
    }

    public function findAllTags(string $locale): array
    {
        return $this->tagRowsQuery($locale)
            ->orderBy('name')
            ->get()
            ->all();
    }

    public function tagsListQuery(string $search, string $locale): QueryBuilder
    {
        return $this->tagRowsQuery($locale)
            ->when($search !== '', fn (QueryBuilder $q): QueryBuilder => $this->applyTagNameSearch($q, $search));
    }

    /**
     * The admin list projection — the locale-resolved title, the
     * reading time and the `article_views` count plus every row field
     * the admin surfaces render (admin-section-ui.md:387-403).
     */
    private function adminRowsQuery(): QueryBuilder
    {
        $locale = app()->getLocale();

        return DB::table('blog_articles as a')
            ->leftJoin('blog_article_translations as pl', function ($join) use ($locale): void {
                $join->on('pl.article_id', '=', 'a.id')
                    ->where('pl.locale', '=', $locale);
            })
            ->leftJoin('blog_article_translations as pe', function ($join): void {
                $join->on('pe.article_id', '=', 'a.id')
                    ->where('pe.locale', '=', 'en');
            })
            ->leftJoin('article_views as av', 'av.article_id', '=', 'a.id')
            ->select([
                'a.id',
                'a.slug',
                DB::raw('coalesce(pl.title, pe.title, \'\') as title'),
                'a.is_published',
                'a.published_at',
                DB::raw('coalesce(pl.reading_time_minutes, pe.reading_time_minutes, 0) as reading_time_minutes'),
                DB::raw('coalesce(av.views, 0) as views'),
                'a.created_at',
                'a.updated_at',
            ]);
    }

    /**
     * The admin title/content search (content-management.md:224) over
     * ALL per-locale translation rows.
     */
    private function applyTextSearch(QueryBuilder $query, string $search): QueryBuilder
    {
        return $query->whereIn('a.id', function ($sub) use ($search): void {
            $sub->select('article_id')
                ->from('blog_article_translations')
                ->where('title', 'ilike', "%{$search}%")
                ->orWhere('content', 'ilike', "%{$search}%");
        });
    }

    /**
     * The `?article_tags=` AND/intersection filter: articles whose
     * article-tag set contains ALL of the submitted tag ids
     * (routes.md:204-205, B2).
     *
     * @param  int[]  $tagIds
     */
    private function applyArticleTagIntersect(QueryBuilder $query, array $tagIds): QueryBuilder
    {
        return $query->whereIn('a.id', function ($sub) use ($tagIds): void {
            $sub->select('article_id')
                ->from('article_tag_pivot')
                ->whereIn('tag_id', $tagIds)
                ->groupBy('article_id')
                ->havingRaw('count(distinct tag_id) = ?', [count($tagIds)]);
        });
    }

    /**
     * The `?skill_tags=` AND/intersection filter — the same intersect
     * over `article_skill_pivot` (combined with the article-tag
     * intersect, the result is the intersection of both sources).
     *
     * @param  int[]  $skillIds
     */
    private function applySkillTagIntersect(QueryBuilder $query, array $skillIds): QueryBuilder
    {
        return $query->whereIn('a.id', function ($sub) use ($skillIds): void {
            $sub->select('article_id')
                ->from('article_skill_pivot')
                ->whereIn('skill_id', $skillIds)
                ->groupBy('article_id')
                ->havingRaw('count(distinct skill_id) = ?', [count($skillIds)]);
        });
    }

    /**
     * The tag list projection — the locale-resolved name plus the
     * `article_tag_pivot` usage count (admin-section-ui.md:315-320).
     */
    private function tagRowsQuery(string $locale): QueryBuilder
    {
        return DB::table('article_tags as t')
            ->leftJoin('article_tag_translations as tl', function ($join) use ($locale): void {
                $join->on('tl.article_tag_id', '=', 't.id')
                    ->where('tl.locale', '=', $locale);
            })
            ->leftJoin('article_tag_translations as te', function ($join): void {
                $join->on('te.article_tag_id', '=', 't.id')
                    ->where('te.locale', '=', 'en');
            })
            ->select([
                't.id',
                't.slug',
                DB::raw('coalesce(tl.name, te.name, \'\') as name'),
                DB::raw('(select count(*) from article_tag_pivot where tag_id = t.id) as articles_use_count'),
            ]);
    }

    /**
     * The tag name search (admin-section-ui.md:315 — across all
     * locales).
     */
    private function applyTagNameSearch(QueryBuilder $query, string $search): QueryBuilder
    {
        return $query->whereIn('t.id', function ($sub) use ($search): void {
            $sub->select('article_tag_id')
                ->from('article_tag_translations')
                ->where('name', 'ilike', "%{$search}%");
        });
    }
}
