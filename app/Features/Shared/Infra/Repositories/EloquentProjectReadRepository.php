<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Shared\Domain\Entities\FeaturedProject;
use App\Features\Shared\Domain\Entities\FeaturedProjectTechTag;
use App\Features\Shared\Domain\RepositoryInterfaces\ProjectReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed read model for featured projects (B11) — consumed by
 * the landing page. Mirrors the TechSkill read pattern: two queries per
 * method (projects + their tech tags separately), locale-resolved
 * translations with an `en` fallback. The Projects feature (7.1) owns
 * the write side and extends the read side; this repository only covers
 * what the landing page needs.
 */
final class EloquentProjectReadRepository implements ProjectReadRepositoryInterface
{
    public function findFeatured(string $locale, int $limit = 5): array
    {
        $rows = DB::table('projects as p')
            ->select([
                'p.id',
                'p.slug',
                'p.preview_image',
                DB::raw("coalesce(pl.title, pe.title, '') as title"),
                DB::raw("coalesce(pl.brief_description, pe.brief_description, '') as brief_description"),
            ])
            ->leftJoin('project_translations as pl', function ($join) use ($locale): void {
                $join->on('pl.project_id', '=', 'p.id')
                    ->where('pl.locale', '=', $locale);
            })
            ->leftJoin('project_translations as pe', function ($join): void {
                $join->on('pe.project_id', '=', 'p.id')
                    ->where('pe.locale', '=', 'en');
            })
            ->where('p.is_published', true)
            ->where('p.is_featured', true)
            ->whereNull('p.deleted_at')
            ->orderBy('p.featured_sort_order')
            ->orderBy('p.id')
            ->limit($limit)
            ->get();

        $tagsByProjectId = $this->techTagsByProjectId($rows->pluck('id')->all());

        $projects = [];
        foreach ($rows as $row) {
            $projects[] = new FeaturedProject(
                id: (int) $row->id,
                title: $row->title,
                slug: $row->slug,
                briefDescription: $row->brief_description,
                previewImage: $row->preview_image,
                techTags: $tagsByProjectId[(int) $row->id] ?? [],
            );
        }

        return $projects;
    }

    public function countPublished(): int
    {
        return DB::table('projects')
            ->where('is_published', true)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * @param  int[]  $projectIds
     * @return array<int, FeaturedProjectTechTag[]>
     */
    private function techTagsByProjectId(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $rows = DB::table('project_tech_tags as pt')
            ->join('skills as s', 's.id', '=', 'pt.skill_id')
            ->select(['pt.project_id', 's.id', 's.name', 's.icon'])
            ->whereIn('pt.project_id', $projectIds)
            ->orderBy('s.name')
            ->get();

        $tags = [];
        foreach ($rows as $row) {
            $projectId = (int) $row->project_id;
            $tags[$projectId][] = new FeaturedProjectTechTag(
                id: (int) $row->id,
                name: $row->name,
                icon: $row->icon,
            );
        }

        return $tags;
    }
}
