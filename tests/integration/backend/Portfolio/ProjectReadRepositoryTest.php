<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Portfolio;

use App\Features\Shared\Infra\Repositories\EloquentProjectReadRepository;
use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use Illuminate\Support\Facades\DB;

function createProject(string $slug, array $attributes = []): int
{
    return DB::table('projects')->insertGetId(array_merge([
        'slug' => $slug,
        'preview_image' => null,
        'preview_image_alt' => null,
        'github_url' => null,
        'live_demo_url' => null,
        'tech_writeup_article_id' => null,
        'is_featured' => false,
        'featured_sort_order' => null,
        'is_published' => true,
        'published_at' => now(),
        'tech_tags_count' => 0,
        'deleted_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function translateProject(int $projectId, string $locale, string $title): void
{
    DB::table('project_translations')->insert([
        'project_id' => $projectId,
        'locale' => $locale,
        'title' => $title,
        'brief_description' => "Brief of $title",
        'full_description' => null,
        'problem' => null,
        'solution' => null,
        'search_vector' => null,
    ]);
}

describe('EloquentProjectReadRepository', function (): void {
    beforeEach(function (): void {
        $this->repository = new EloquentProjectReadRepository();
    });

    it('returns only published + featured projects ordered by featured_sort_order', function (): void {
        createProject('a', ['is_featured' => true, 'featured_sort_order' => 2]);
        createProject('b', ['is_featured' => true, 'featured_sort_order' => 1]);
        createProject('c', ['is_featured' => true, 'featured_sort_order' => null]);
        createProject('d', ['is_featured' => false]);
        createProject('e', ['is_featured' => true, 'featured_sort_order' => 0, 'deleted_at' => now()]);

        foreach (['a', 'b', 'c'] as $slug) {
            translateProject(DB::table('projects')->where('slug', $slug)->value('id'), 'en', "Project $slug");
        }

        $projects = $this->repository->findFeatured('en');

        expect($projects)->toHaveCount(3)
            ->and(array_map(fn ($p): string => $p->toSlug(), $projects))->toBe(['b', 'a', 'c'])
            ->and($projects[0]->toPreviewImage())->toBeNull();
    });

    it('resolves titles per locale with an en fallback', function (): void {
        $id = createProject('x', ['is_featured' => true, 'featured_sort_order' => 0]);
        translateProject($id, 'en', 'Project X');
        translateProject($id, 'ru', 'Проект Икс');

        expect($this->repository->findFeatured('ru')[0]->toTitle())->toBe('Проект Икс')
            ->and($this->repository->findFeatured('de')[0]->toTitle())->toBe('Project X');
    });

    it('attaches the tech tags from the skills table', function (): void {
        $group = SkillGroupModel::factory()->create();
        $laravel = SkillModel::factory()->create([
            'skill_group_id' => $group->id, 'name' => 'Laravel', 'icon' => 'devicon:laravel', 'sort_order' => 0,
        ]);
        $php = SkillModel::factory()->create([
            'skill_group_id' => $group->id, 'name' => 'PHP', 'icon' => 'devicon:php', 'sort_order' => 1,
        ]);

        $id = createProject('x', ['is_featured' => true, 'featured_sort_order' => 0]);
        translateProject($id, 'en', 'Project X');
        DB::table('project_tech_tags')->insert([
            ['project_id' => $id, 'skill_id' => $laravel->id],
            ['project_id' => $id, 'skill_id' => $php->id],
        ]);

        $project = $this->repository->findFeatured('en')[0];

        expect($project->toTechTags())->toHaveCount(2)
            ->and($project->toTechTags()[0]->toSkillName())->toBe('Laravel')
            ->and($project->toTechTags()[0]->toIcon())->toBe('devicon:laravel');
    });

    it('counts published, non-trashed projects only', function (): void {
        createProject('a');
        createProject('b');
        createProject('c', ['deleted_at' => now()]);
        createProject('d', ['is_published' => false]);

        expect($this->repository->countPublished())->toBe(2);
    });

    it('caps the featured result at the requested limit', function (): void {
        createProject('a', ['is_featured' => true, 'featured_sort_order' => 0]);
        createProject('b', ['is_featured' => true, 'featured_sort_order' => 1]);
        createProject('c', ['is_featured' => true, 'featured_sort_order' => 2]);

        foreach (['a', 'b', 'c'] as $slug) {
            translateProject(DB::table('projects')->where('slug', $slug)->value('id'), 'en', "Project $slug");
        }

        expect($this->repository->findFeatured('en', 2))->toHaveCount(2);
    });
});
