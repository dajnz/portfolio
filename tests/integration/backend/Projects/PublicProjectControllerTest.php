<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Projects;

use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use App\Features\Settings\Infra\Models\SettingModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use JsonException;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

/**
 * Seeds a published project with both locales and the given
 * base/translation overrides.
 */
function publicProject(array $attributes = [], array $translations = []): ProjectModel
{
    $project = ProjectModel::factory()->published()->create($attributes);

    $defaults = [
        'en' => ['title' => 'Payments Gateway', 'brief_description' => 'A payment gateway.'],
        'ru' => ['title' => 'Платёжный шлюз', 'brief_description' => 'Платёжный шлюз.'],
    ];

    foreach (array_merge($defaults, $translations) as $locale => $fields) {
        ProjectTranslationModel::factory()
            ->forLocale($locale)
            ->create(['project_id' => $project->id] + $fields);
    }

    return $project;
}

/** Decodes the opaque next_cursor into its `{id, v}` parts. */
function decodedProjectCursor(string $cursor): array
{
    $json = base64_decode(strtr($cursor, '-_', '+/'), true);

    try {
        return json_decode((string) $json, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
}

/**
 * Seeds a skill in a group and returns its id.
 */
function publicSkillId(string $name = 'PHP'): int
{
    $group = SkillGroupModel::factory()->create();

    return SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => $name])->id;
}

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);

    // 2 items per page makes the cursor pagination assertions cheap.
    SettingModel::query()->updateOrCreate(
        ['key' => 'projects_per_page'],
        ['value' => '2', 'type' => 'integer'],
    );
});

describe('PublicProjectController', function (): void {
    it('serves the projects index ordered by published_at desc with seo props', function (): void {
        $older = publicProject(['published_at' => now()->subDays(30)]);
        $newer = publicProject([
            'published_at' => now(),
            'github_url' => 'https://github.com/example/new',
            'is_featured' => true,
            'featured_sort_order' => 0,
        ]);

        ProjectScreenshotModel::factory()->create([
            'project_id' => $newer->id,
            'image_path' => 'projects/shot.png',
            'alt_text' => ['en' => 'Dashboard'],
            'sort_order' => 0,
        ]);

        $this->get('/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Public/Pages/ProjectsIndexPage')
                ->has('projects', 2)
                ->where('projects.0.id', $newer->id)
                ->where('projects.0.title', 'Payments Gateway')
                ->where('projects.0.brief_description', 'A payment gateway.')
                ->where('projects.0.slug', $newer->slug)
                ->where('projects.0.published_at', $newer->published_at->toIso8601String())
                ->where('projects.0.is_featured', true)
                ->where('projects.0.github_url', 'https://github.com/example/new')
                ->where('projects.0.screenshots.0.image_path', 'projects/shot.png')
                ->where('projects.0.screenshots.0.alt_text.en', 'Dashboard')
                ->where('projects.1.id', $older->id)
                ->where('projects.1.screenshots', [])
                // 2 items = a full batch at the seeded per_page (2):
                // a next cursor is offered even though the list is done.
                ->where('has_more', true)
                ->where('next_cursor', fn (mixed $cursor) => is_string($cursor) && $cursor !== '')
                ->where('seo.title', 'Projects — ')
                ->where('seo.description', 'Projects and case studies by .')
                ->where('seo.canonical_url', url('/projects'))
                ->where('seo.og_url', url('/projects'))
                ->where('seo.og_type', 'website')
                ->has('skill_tags', 0));
    });

    it('serves the index with the skill_tags options', function (): void {
        $php = publicSkillId('PHP');

        publicProject();

        $this->get('/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->has('skill_tags', 1)
                ->where('skill_tags.0.id', $php)
                ->where('skill_tags.0.name', 'PHP'));
    });

    it('excludes unpublished and trashed projects', function (): void {
        publicProject();
        publicProject(['is_published' => false, 'published_at' => null]);
        publicProject(['deleted_at' => now()]);

        $this->get('/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1));
    });

    it('paginates with the opaque composite cursor (date sort)', function (): void {
        $a = publicProject(['published_at' => now()->subDays(5)]);
        $b = publicProject(['published_at' => now()->subDays(4)]);
        $c = publicProject(['published_at' => now()->subDays(3)]);

        $first = $this->get('/projects');
        $first->assertInertia(fn (Assert $page) => $page
            ->has('projects', 2)
            ->where('projects.0.id', $c->id)
            ->where('projects.1.id', $b->id)
            ->where('next_cursor', fn (mixed $cursor) => is_string($cursor) && $cursor !== ''));

        // The next cursor carries the last item's (sort_value, id) —
        // built with the same codec the controller uses.
        $cursor = app(\App\Features\Shared\Infra\Persistence\PublicCursorCodec::class)
            ->encode(new \App\Features\Shared\Infra\Persistence\Cursor(
                id: $b->id,
                sortValue: $b->published_at->format('Y-m-d H:i:s'),
            ));

        // The "Load More" request resumes after the cursor: the batch
        // containing it (none — it was the last of the first batch) plus
        // one beyond → item `a`.
        $this->get('/projects?cursor='.$cursor)
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 3)
                ->where('projects.2.id', $a->id)
                // The final batch (1 item < perPage) is incomplete — no
                // more cursors.
                ->where('has_more', false)
                ->where('next_cursor', null));
    });

    it('sorts newest-last via ?direction=asc with the reversed keyset cursor', function (): void {
        $oldest = publicProject(['published_at' => now()->subDays(5)]);
        $middle = publicProject(['published_at' => now()->subDays(4)]);
        $newest = publicProject(['published_at' => now()->subDays(3)]);

        $first = $this->get('/projects?direction=asc');
        $first->assertInertia(fn (Assert $page) => $page
            ->has('projects', 2)
            // Ascending: the oldest items first (the `desc` default is
            // covered by the date-sort pagination test above).
            ->where('projects.0.id', $oldest->id)
            ->where('projects.1.id', $middle->id)
            ->where('next_cursor', fn (mixed $cursor) => is_string($cursor) && $cursor !== ''));

        $cursor = app(\App\Features\Shared\Infra\Persistence\PublicCursorCodec::class)
            ->encode(new \App\Features\Shared\Infra\Persistence\Cursor(
                id: $middle->id,
                sortValue: $middle->published_at->format('Y-m-d H:i:s'),
            ));

        $this->get('/projects?direction=asc&cursor='.$cursor)
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 3)
                ->where('projects.2.id', $newest->id)
                ->where('next_cursor', null));

        // An invalid direction falls back to the desc default.
        $this->get('/projects?direction=sideways')
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.id', $newest->id));
    });

    it('filters by the tech-tag AND/intersection', function (): void {
        $php = publicSkillId('PHP');
        $go = publicSkillId('Go');
        $both = publicProject();
        $both->techTags()->attach([$php, $go]);
        $phpOnly = publicProject();
        $phpOnly->techTags()->attach([$php]);

        $this->get('/projects?tags='.$php.','.$go)
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $both->id)
                ->where('projects.0.tech_tags.0.id', $php)
                ->where('projects.0.tech_tags.1.id', $go)
                ->where('next_cursor', null));
    });

    it('searches with FTS ranking when ?q= is present', function (): void {
        publicProject([], [
            'en' => ['title' => 'Payments Gateway', 'brief_description' => 'Handles online payments.'],
        ]);
        publicProject([], [
            'en' => ['title' => 'Blog Platform', 'brief_description' => 'Markdown publishing.'],
        ]);

        $this->get('/projects?q=payments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.title', 'Payments Gateway')
                ->where('next_cursor', null));
    });

    it('returns the JSON dual-response variant with pagination meta and raw fields', function (): void {
        $a = publicProject();
        $b = publicProject();

        $this->getJson('/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $b->id)
            ->assertJsonPath('data.0.title', 'Payments Gateway')
            ->assertJsonPath('data.0.slug', $b->slug)
            ->assertJsonPath('data.0.published_at', $b->published_at->toIso8601String())
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonStructure(['data' => [['id', 'slug', 'title', 'brief_description', 'published_at', 'preview_image', 'github_url', 'live_demo_url', 'is_featured', 'tech_tags']], 'meta' => ['current_page', 'from', 'to', 'per_page', 'last_page', 'total']]);
    });

    it('honors page/per_page/sort/tags/q in the JSON variant', function (): void {
        $a = publicProject();
        $b = publicProject();
        publicProject();

        $this->getJson('/projects?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 3);

        $php = publicSkillId('PHP');
        $tagged = publicProject();
        $tagged->techTags()->attach([$php]);

        $this->getJson('/projects?tags='.$php)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tagged->id);
    });

    it('serves the project detail with the locale-resolved fields', function (): void {
        $project = publicProject(['github_url' => 'https://github.com/example/gateway'], [
            'en' => ['title' => 'Payments Gateway', 'brief_description' => 'A payment gateway.', 'full_description' => '**Full** markdown.', 'problem' => 'Slow payouts.', 'solution' => 'Queued processing.'],
        ]);

        $this->get('/projects/'.$project->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Public/Pages/ProjectDetailPage')
                ->where('project.id', $project->id)
                ->where('project.title', 'Payments Gateway')
                ->where('project.full_description', "<p><strong>Full</strong> markdown.</p>\n")
                ->where('project.problem', "<p>Slow payouts.</p>\n")
                ->where('project.solution', "<p>Queued processing.</p>\n")
                ->where('project.github_url', 'https://github.com/example/gateway')
                ->where('project.tech_writeup', null));
    });

    it('renders the detail bodies through the safe content pipeline', function (): void {
        $project = publicProject([], [
            'en' => [
                'full_description' => '**Bold** <script>alert(1)</script>',
                'problem' => '# Heading',
                'solution' => '`code`',
            ],
        ]);

        $this->get('/projects/'.$project->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Public/Pages/ProjectDetailPage')
                ->where('project.full_description', fn (string $html) => str_contains($html, '<strong>Bold</strong>')
                    && str_contains($html, '&lt;script&gt;')
                    && ! str_contains($html, '<script>'))
                ->where('project.problem', fn (string $html) => str_contains($html, '<h1 id="heading">Heading</h1>'))
                ->where('project.solution', fn (string $html) => str_contains($html, '<code>code</code>')));
    });

    it('serves the detail with the tech tags and the nested editors', function (): void {
        $php = publicSkillId('PHP');
        $project = publicProject(['tech_tags_count' => 1]);
        $project->techTags()->attach([$php]);

        ProjectScreenshotModel::factory()->create([
            'project_id' => $project->id,
            'image_path' => 'projects/shot.png',
            'alt_text' => ['en' => 'Dashboard', 'ru' => 'Панель'],
            'title' => ['en' => 'Overview'],
            'description' => ['en' => 'The dashboard.'],
            'sort_order' => 0,
        ]);

        $detailItem = \App\Features\Projects\Infra\Models\ProjectDetailItemModel::factory()->create(['project_id' => $project->id]);
        \App\Features\Projects\Infra\Models\ProjectDetailItemTranslationModel::factory()->forLocale('en')->create([
            'detail_item_id' => $detailItem->id,
            'title' => 'Testing',
            'description' => 'Full suite.',
        ]);

        $this->get('/projects/'.$project->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.tech_tags.0.id', $php)
                ->where('project.screenshots.0.image_path', 'projects/shot.png')
                ->where('project.screenshots.0.alt_text.en', 'Dashboard')
                ->where('project.screenshots.0.title.en', 'Overview')
                ->where('project.detail_items.0.title', 'Testing')
                ->where('project.detail_items.0.description', 'Full suite.'));
    });

    it('serves the detail with the SEO props and the JSON-LD', function (): void {
        $php = publicSkillId('PHP');
        $project = publicProject([
            'github_url' => 'https://github.com/example/gateway',
            'tech_tags_count' => 1,
            'preview_image' => 'projects/cover.png',
        ]);
        $project->techTags()->attach([$php]);

        $this->get('/projects/'.$project->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.title', 'Payments Gateway — ')
                ->where('seo.canonical_url', url('/projects/'.$project->slug))
                ->where('seo.og_url', url('/projects/'.$project->slug))
                ->where('seo.og_type', 'article')
                ->where('seo.og_image', url('/assets/projects/cover.png'))
                ->where('seo.json_ld.0.@type', 'SoftwareSourceCode')
                ->where('seo.json_ld.0.codeRepository', 'https://github.com/example/gateway')
                ->where('seo.json_ld.0.programmingLanguage.0', 'PHP')
                ->where('seo.json_ld.1.@type', 'BreadcrumbList'));
    });

    it('resolves the ru locale row and serves the locale-prefixed route', function (): void {
        publicProject();

        $this->get('/ru/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.title', 'Платёжный шлюз'));
    });

    it('returns 404 for unpublished projects (not 200 + noindex)', function (): void {
        $draft = publicProject(['is_published' => false, 'published_at' => null]);

        $this->get('/projects/'.$draft->slug)
            ->assertNotFound();
    });

    it('returns 404 for unknown slugs', function (): void {
        $this->get('/projects/does-not-exist')
            ->assertNotFound();
    });

    it('redirects old slugs with a 301 to the current slug', function (): void {
        $project = publicProject(['slug' => 'current-slug']);

        DB::table('old_slugs')->insert([
            'old_slug' => 'old-slug',
            'entity_type' => 'project',
            'entity_id' => $project->id,
        ]);

        $this->get('/projects/old-slug')
            ->assertRedirect(url('/projects/current-slug'))
            ->assertStatus(301);

        // The locale-prefixed variant keeps the prefix.
        $this->get('/ru/projects/old-slug')
            ->assertRedirect(url('/ru/projects/current-slug'))
            ->assertStatus(301);
    });

    it('re-points a reverted slug via the old_slugs upsert (A→B→A→B)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = publicProject(['slug' => 'slug-a']);

        $update = function (string $slug) use ($project): void {
            $this->put("/admin/projects/{$project->id}", [
                'slug' => $slug,
                'title' => ['en' => 'Title A', 'ru' => 'Название А'],
                'brief_description' => ['en' => 'Brief.', 'ru' => 'Кратко.'],
                'full_description' => ['en' => null, 'ru' => null],
                'problem' => ['en' => null, 'ru' => null],
                'solution' => ['en' => null, 'ru' => null],
                'preview_image' => null,
                'preview_image_alt' => ['en' => null, 'ru' => null],
                'github_url' => null,
                'live_demo_url' => null,
                'is_featured' => false,
                'featured_sort_order' => null,
                'is_published' => false,
                'tech_tags' => [],
                'screenshots' => [],
                'detail_items' => [],
                'tech_writeup_article_id' => null,
            ])->assertRedirect()
                ->assertSessionHasNoErrors();
        };

        // A→B records the old slug `slug-a`.
        $update('slug-b');
        expect(DB::table('old_slugs')->where('old_slug', 'slug-a')->where('entity_type', 'project')->count())->toBe(1);

        // B→A records `slug-b`; the main table's `slug-a` now shadows the old row.
        $update('slug-a');
        expect(DB::table('old_slugs')->where('old_slug', 'slug-b')->where('entity_type', 'project')->count())->toBe(1);

        // A→B records `slug-a` AGAIN — the upsert re-points the existing
        // row instead of violating the (old_slug, entity_type) unique
        // constraint (database.md:451).
        $update('slug-b');
        expect(DB::table('old_slugs')->where('old_slug', 'slug-a')->where('entity_type', 'project')->count())->toBe(1);

        $this->get('/projects/slug-a')
            ->assertRedirect(url('/projects/slug-b'))
            ->assertStatus(301);
    });

    it('hides the tech-writeup link while the linked article is unpublished', function (): void {
        $project = publicProject();
        $articleId = DB::table('blog_articles')->insertGetId([
            'slug' => 'writeup', 'is_published' => false, 'published_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $project->update(['tech_writeup_article_id' => $articleId]);

        $this->get('/projects/'.$project->slug)
            ->assertInertia(fn (Assert $page) => $page->where('project.tech_writeup', null));

        // Re-publishing the article brings the link back.
        DB::table('blog_articles')->where('id', $articleId)->update(['is_published' => true]);
        DB::table('blog_article_translations')->insert([
            'article_id' => $articleId, 'locale' => 'en',
            'title' => 'The Writeup', 'short_description' => 'Brief', 'content' => 'Content',
            'reading_time_minutes' => 1,
        ]);

        $this->get('/projects/'.$project->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.tech_writeup.id', $articleId)
                ->where('project.tech_writeup.slug', 'writeup'));
    });
});
