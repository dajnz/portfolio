<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Shared\Domain\Entities\FeaturedProject;
use App\Features\Shared\Domain\Entities\TechSkillTag;
use App\Features\Shared\Domain\RepositoryInterfaces\ProjectReadRepositoryInterface;
use App\Features\Shared\Domain\ValueObjects\SkillIcon;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use App\Features\Shared\Domain\ValueObjects\StoredThumbnail;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed read model for featured projects — consumed by the landing
 * page. Mirrors the TechSkill read pattern: the projects, their tech tags and
 * their derived covers are read in separate queries, with locale-resolved
 * translations and an `en` fallback. The Projects feature owns the write side
 * and extends the read side; this repository only covers what the landing page
 * needs.
 */
final class EloquentProjectReadRepository implements ProjectReadRepositoryInterface
{
    public function findFeatured(string $locale, int $limit = 5): array
    {
        $rows = DB::table('projects as p')
            ->select([
                'p.id',
                'p.slug',
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

        $projectIds = $rows->pluck('id')->all();
        $tagsByProjectId = $this->techTagsByProjectId($projectIds);
        $coversByProjectId = $this->coverPicturesByProjectId($projectIds);

        $projects = [];
        foreach ($rows as $row) {
            $projects[] = new FeaturedProject(
                id: (int) $row->id,
                title: $row->title,
                slug: $row->slug,
                briefDescription: $row->brief_description,
                coverImage: $coversByProjectId[(int) $row->id] ?? null,
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
     * @return array<int, StoredPicture>  Keyed by project ID; missing
     *     when the project has no screenshots.
     */
    private function coverPicturesByProjectId(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }

        $covers = [];
        foreach (DB::table('project_screenshots')
            ->selectRaw('distinct on (project_id) project_id, image_path, title, thumbnail_path')
            ->whereIn('project_id', $projectIds)
            ->orderBy('project_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get() as $row) {
            $covers[(int) $row->project_id] = new StoredPicture(
                url: (string) $row->image_path,
                altLabels: $this->altLabels($row->title),
                thumbnail: StoredThumbnail::fromSource((string) $row->image_path, $row->thumbnail_path),
            );
        }

        return $covers;
    }

    /**
     * @return array<string, string>
     */
    private function altLabels(mixed $title): array
    {
        $labels = [];
        foreach ((array) json_decode((string) $title, true) as $locale => $alt) {
            if (is_string($alt) && $alt !== '') {
                $labels[(string) $locale] = $alt;
            }
        }

        return $labels;
    }

    /**
     * @param  int[]  $projectIds
     * @return array<int, TechSkillTag[]>
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
            $tags[$projectId][] = new TechSkillTag(
                id: (int) $row->id,
                skillName: $row->name,
                icon: $row->icon === null ? null : new SkillIcon($row->icon),
            );
        }

        return $tags;
    }
}
