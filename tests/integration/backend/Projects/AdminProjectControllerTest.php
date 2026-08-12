<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Projects;

use App\Features\Projects\Infra\Models\ProjectDetailItemModel;
use App\Features\Projects\Infra\Models\ProjectDetailItemTranslationModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
});

/**
 * The shared store/update payload — title/brief_description required
 * per active locale, plus the media/relations/engineering sections.
 *
 * @return array<string, mixed>
 */
function projectPayload(array $overrides = []): array
{
    return array_merge([
        'slug' => 'payments-gateway',
        'title' => ['en' => 'Payments Gateway', 'ru' => 'Платёжный шлюз'],
        'brief_description' => ['en' => 'A payment processing gateway.', 'ru' => 'Платёжный шлюз для обработки платежей.'],
        'full_description' => ['en' => '**Full** markdown.', 'ru' => '**Полное** описание.'],
        'problem' => ['en' => 'Slow payouts.', 'ru' => 'Медленные выплаты.'],
        'solution' => ['en' => 'Queued processing.', 'ru' => 'Обработка через очередь.'],
        'preview_image' => 'projects/preview.png',
        'preview_image_alt' => ['en' => 'Gateway dashboard', 'ru' => 'Панель шлюза'],
        'github_url' => 'https://github.com/example/gateway',
        'live_demo_url' => 'https://demo.example.com',
        'is_featured' => false,
        'featured_sort_order' => null,
        'is_published' => false,
        'tech_tags' => [],
        'screenshots' => [],
        'detail_items' => [],
        'tech_writeup_article_id' => null,
    ], $overrides);
}

/**
 * Creates a fully translated project with the given base attributes.
 */
function projectItem(array $attributes = [], array $translations = []): ProjectModel
{
    $project = ProjectModel::factory()->create($attributes);

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

/**
 * Seeds a skill in a group and returns its id.
 */
function projectSkillId(string $name = 'PHP'): int
{
    $group = SkillGroupModel::factory()->create();

    return SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => $name])->id;
}

describe('AdminProjectController', function (): void {
    it('requires authentication for the list and mutations', function (): void {
        $this->get('/admin/projects')->assertRedirect('/admin/login');
        $this->get('/admin/projects/create')->assertRedirect('/admin/login');
        $this->get('/admin/projects/1/edit')->assertRedirect('/admin/login');
        $this->post('/admin/projects', projectPayload())->assertRedirect('/admin/login');
        $this->put('/admin/projects/1', projectPayload())->assertRedirect('/admin/login');
        $this->delete('/admin/projects/1')->assertRedirect('/admin/login');
        $this->patch('/admin/projects/1/restore')->assertRedirect('/admin/login');
        $this->delete('/admin/projects/1/force')->assertRedirect('/admin/login');
        $this->post('/admin/projects/reorder-featured', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->patch('/admin/projects/1/feature')->assertRedirect('/admin/login');
        $this->patch('/admin/projects/1/unfeature')->assertRedirect('/admin/login');
        $this->patch('/admin/projects/1/tech-writeup', ['tech_writeup_article_id' => null])->assertRedirect('/admin/login');
        $this->post('/admin/projects/1/tags', ['skill_id' => 1])->assertRedirect('/admin/login');
        $this->delete('/admin/projects/1/tags/1')->assertRedirect('/admin/login');
        $this->patch('/admin/projects/1/publish')->assertRedirect('/admin/login');
        $this->patch('/admin/projects/1/unpublish')->assertRedirect('/admin/login');
        $this->post('/admin/projects/bulk-publish', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->post('/admin/projects/bulk-unpublish', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->post('/admin/projects/bulk-delete', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->post('/admin/projects/bulk-restore', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->post('/admin/projects/bulk-force-delete', ['ids' => [1]])->assertRedirect('/admin/login');
    });

    it('lists projects with the current-locale fields and pagination meta', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $older = projectItem(['created_at' => now()->subDays(10)]);
        $newer = projectItem([
            'created_at' => now(),
            'is_published' => true,
            'is_featured' => true,
            'featured_sort_order' => 0,
            'tech_tags_count' => 3,
        ], [
            'en' => ['title' => 'Auth Service'],
        ]);

        $this->get('/admin/projects')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Admin/Pages/ProjectsListPage')
                ->has('projects', 2)
                ->where('projects.0.id', $newer->id)
                ->where('projects.0.title', 'Auth Service')
                ->where('projects.0.slug', $newer->slug)
                ->where('projects.0.is_published', true)
                ->where('projects.0.is_featured', true)
                ->where('projects.0.featured_sort_order', 0)
                ->where('projects.0.tech_tags_count', 3)
                ->where('projects.1.id', $older->id)
                ->where('projects.1.title', 'Payments Gateway')
                ->where('meta.total', 2)
                ->where('meta.per_page', 12)
                ->where('meta.current_page', 1)
                ->where('meta.sort_column', 'id')
                ->where('meta.sort_direction', 'desc')
                ->where('meta.cursors.first.id', $newer->id)
                ->where('meta.cursors.last.id', $older->id));
    });

    it('falls back to the en title when the current locale row is missing', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        projectItem([], ['en' => ['title' => 'English Only']]);

        $this->get('/admin/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.title', 'English Only'));
    });

    it('hides trashed projects by default and shows them with ?trashed=1', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $live = projectItem();
        $trashed = projectItem(['deleted_at' => now()]);

        $this->get('/admin/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $live->id)
                ->where('meta.total', 1));

        $this->get('/admin/projects?trashed=1')
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $trashed->id)
                ->where('meta.total', 1));
    });

    it('searches by title across the per-locale translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        projectItem([], ['en' => ['title' => 'Site Reliability Toolkit'], 'ru' => ['title' => 'Инструментарий надёжности']]);
        projectItem();

        $this->get('/admin/projects?q=reliability')
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.title', 'Site Reliability Toolkit')
                ->where('meta.total', 1));

        $this->get('/admin/projects?q=Инструментарий')
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('meta.total', 1));
    });

    it('filters the list by the featured toggle', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $featured = projectItem(['is_featured' => true, 'featured_sort_order' => 0]);
        projectItem();

        $this->get('/admin/projects?featured=1')
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $featured->id));
    });

    it('filters the list by the published status', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $published = projectItem(['is_published' => true, 'published_at' => now()]);
        projectItem();

        $this->get('/admin/projects?published=1')
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $published->id));

        $this->get('/admin/projects?published=0')
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $published->id + 1));
    });

    it('filters the list by the tech-tag AND/intersection', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $tagged = projectItem();
        $php = projectSkillId('PHP');
        $go = projectSkillId('Go');
        $tagged->techTags()->attach([$php, $go]);

        // AND/intersection: the project must carry BOTH tags.
        $this->get('/admin/projects?tags='.$php.','.$go)
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $tagged->id));

        $this->get('/admin/projects?tags='.$php)
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $tagged->id));
    });

    it('serves the JSON autocomplete variant with length-aware pagination', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem([], ['en' => ['title' => 'Portfolio Platform']]);
        projectItem();

        $this->getJson('/admin/projects?q=port&per_page=8')
            ->assertOk()
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.title', 'Portfolio Platform')
            ->assertJsonPath('data.0.slug', $project->slug)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 8)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonStructure(['data' => [['id', 'title', 'slug', 'published_at']], 'meta' => ['current_page', 'from', 'to', 'per_page', 'last_page', 'total']]);
    });

    it('serves the create form with the skill picker options', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $php = projectSkillId('PHP');

        $this->get('/admin/projects/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Admin/Pages/ProjectFormPage')
                ->where('project', null)
                ->has('skills', 1)
                ->where('skills.0.id', $php)
                ->where('skills.0.name', 'PHP'));
    });

    it('stores a project with the shared fields and the per-locale translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', projectPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $project = ProjectModel::query()->first();
        expect($project)->not->toBeNull()
            ->and($project->slug)->toBe('payments-gateway')
            ->and($project->is_published)->toBeFalse()
            ->and($project->is_featured)->toBeFalse()
            ->and($project->preview_image)->toBe('projects/preview.png');

        $translations = $project->translations()->get()->keyBy('locale');
        expect($translations->get('en')->title)->toBe('Payments Gateway')
            ->and($translations->get('en')->brief_description)->toBe('A payment processing gateway.')
            ->and($translations->get('en')->full_description)->toBe('**Full** markdown.')
            ->and($translations->get('ru')->title)->toBe('Платёжный шлюз')
            ->and($translations->get('en')->problem)->toBe('Slow payouts.')
            ->and($translations->get('en')->solution)->toBe('Queued processing.');
    });

    it('stores the tech tags with the persisted count', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $php = projectSkillId('PHP');

        $this->post('/admin/projects', projectPayload([
            'tech_tags' => [$php],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $project = ProjectModel::query()->first();
        expect(DB::table('project_tech_tags')->where('project_id', $project->id)->pluck('skill_id')->all())
            ->toBe([$php])
            ->and($project->fresh()->tech_tags_count)->toBe(1);
    });

    it('stores the screenshot gallery in the submitted order', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', projectPayload([
            'screenshots' => [
                [
                    'image' => 'projects/shot-1.png',
                    'alt_text' => ['en' => 'Dashboard', 'ru' => 'Панель'],
                    'title' => ['en' => 'Overview', 'ru' => 'Обзор'],
                    'description' => ['en' => 'The dashboard.', 'ru' => 'Панель управления.'],
                ],
                [
                    'image' => 'projects/shot-2.png',
                    'alt_text' => ['en' => 'Settings'],
                    'title' => [],
                    'description' => [],
                ],
            ],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $screenshots = ProjectModel::query()->first()->screenshots()->get();
        expect($screenshots)->toHaveCount(2)
            ->and($screenshots->first()->image_path)->toBe('projects/shot-1.png')
            ->and($screenshots->first()->alt_text)->toBe(['en' => 'Dashboard', 'ru' => 'Панель'])
            ->and($screenshots->first()->title)->toBe(['en' => 'Overview', 'ru' => 'Обзор'])
            ->and($screenshots->first()->description)->toBe(['en' => 'The dashboard.', 'ru' => 'Панель управления.'])
            ->and($screenshots->first()->sort_order)->toBe(0)
            ->and($screenshots->get(1)->sort_order)->toBe(1);
    });

    it('stores the engineering detail items with their translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', projectPayload([
            'detail_items' => [
                [
                    'icon' => 'heroicons:shield-check',
                    'title' => ['en' => 'Testing', 'ru' => 'Тестирование'],
                    'description' => ['en' => 'Full suite.', 'ru' => 'Полный набор тестов.'],
                ],
            ],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $detailItem = ProjectModel::query()->first()->detailItems()->with('translations')->first();
        expect($detailItem->icon)->toBe('heroicons:shield-check')
            ->and($detailItem->translations()->where('locale', 'en')->first()->title)->toBe('Testing')
            ->and($detailItem->translations()->where('locale', 'ru')->first()->title)->toBe('Тестирование');
    });

    it('keeps published_at null on store when the project is published via the form', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', projectPayload([
            'is_published' => true,
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(ProjectModel::query()->first()->published_at)->not->toBeNull();
    });

    it('validates the required per-locale fields and the slug rules', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', [
            'slug' => 'Bad Slug!',
            'title' => [],
            'brief_description' => [],
        ])->assertRedirect()
            ->assertSessionHasErrors(['slug', 'title.en', 'title.ru', 'brief_description.en', 'brief_description.ru']);

        projectItem(['slug' => 'taken-slug']);

        $this->post('/admin/projects', projectPayload(['slug' => 'taken-slug']))
            ->assertRedirect()
            ->assertSessionHasErrors('slug');
    });

    it('rejects a featured project without a featured sort order', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', projectPayload([
            'is_featured' => true,
            'featured_sort_order' => null,
        ]))->assertRedirect()
            ->assertSessionHasErrors('featured_sort_order');

        $this->post('/admin/projects', projectPayload([
            'is_featured' => true,
            'featured_sort_order' => 0,
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();
    });

    it('validates the engineering icons and the tech-writeup publication', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', projectPayload([
            'detail_items' => [
                ['icon' => 'heroicons:not-in-the-set', 'title' => ['en' => 'X'], 'description' => ['en' => 'Y']],
            ],
        ]))->assertRedirect()
            ->assertSessionHasErrors('detail_items.0.icon');

        DB::table('blog_articles')->insert([
            'slug' => 'draft-article', 'is_published' => false, 'published_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $draftId = DB::table('blog_articles')->max('id');

        $this->post('/admin/projects', projectPayload([
            'tech_writeup_article_id' => $draftId,
        ]))->assertRedirect()
            ->assertSessionHasErrors('tech_writeup_article_id');
    });

    it('appends a numeric suffix to an auto-generated slug collision (A3)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        projectItem(['slug' => 'payments-gateway']);
        projectItem(['slug' => 'payments-gateway-2']);

        // The client auto-generates the naive slug of the EN title — the
        // server re-resolves it and suffixes the collision.
        $this->post('/admin/projects', projectPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(ProjectModel::query()->orderByDesc('id')->first()->slug)->toBe('payments-gateway-3');
    });

    it('keeps a manual slug override as-is (A3)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', projectPayload([
            'slug' => 'my-custom-slug',
            'title' => ['en' => 'Payments Gateway', 'ru' => 'Шлюз'],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(ProjectModel::query()->first()->slug)->toBe('my-custom-slug');
    });

    it('serves the edit form with the per-locale maps and the meta fields', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $php = projectSkillId('PHP');
        $project = projectItem([
            'preview_image' => 'projects/preview.png',
            'preview_image_alt' => ['en' => 'Dashboard', 'ru' => 'Панель'],
            'is_published' => true,
            'published_at' => now(),
            'is_featured' => true,
            'featured_sort_order' => 2,
        ]);
        $project->techTags()->attach([$php]);

        $this->get("/admin/projects/{$project->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Admin/Pages/ProjectFormPage')
                ->where('project.id', $project->id)
                ->where('project.slug', $project->slug)
                ->where('project.title.en', 'Payments Gateway')
                ->where('project.title.ru', 'Платёжный шлюз')
                ->where('project.brief_description.en', 'A payment gateway.')
                ->where('project.preview_image', 'projects/preview.png')
                ->where('project.preview_image_alt.en', 'Dashboard')
                ->where('project.is_published', true)
                ->where('project.is_featured', true)
                ->where('project.featured_sort_order', 2)
                ->where('project.tech_tags.0.id', $php));
    });

    it('serves the edit form with the nested screenshot editor rows', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem();
        $screenshot = ProjectScreenshotModel::factory()->create([
            'project_id' => $project->id,
            'image_path' => 'projects/shot.png',
            'alt_text' => ['en' => 'Alt'],
            'title' => ['ru' => 'Заголовок'],
            'description' => null,
            'sort_order' => 0,
        ]);

        $this->get("/admin/projects/{$project->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.screenshots.0.id', $screenshot->id)
                ->where('project.screenshots.0.image_path', 'projects/shot.png')
                ->where('project.screenshots.0.alt_text.en', 'Alt')
                ->where('project.screenshots.0.title.ru', 'Заголовок'));
    });

    it('serves the edit form with the nested engineering detail editor rows', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem();
        $detailItem = ProjectDetailItemModel::factory()->create(['project_id' => $project->id]);
        ProjectDetailItemTranslationModel::factory()->forLocale('en')->create([
            'detail_item_id' => $detailItem->id,
            'title' => 'Testing',
            'description' => 'Full suite.',
        ]);

        $this->get("/admin/projects/{$project->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('project.detail_items.0.id', $detailItem->id)
                ->where('project.detail_items.0.icon', $detailItem->icon)
                ->where('project.detail_items.0.title.en', 'Testing')
                ->where('project.detail_items.0.description.en', 'Full suite.'));
    });

    it('updates the shared fields and keeps published_at immutable (A2)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem(['is_published' => true, 'published_at' => now()]);

        $this->put("/admin/projects/{$project->id}", projectPayload([
            'slug' => $project->slug,
            'title' => ['en' => 'Payments Hub', 'ru' => 'Платёжный хаб'],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $project->fresh();
        expect($fresh->translations()->where('locale', 'en')->first()->title)->toBe('Payments Hub')
            // `published_at` is immutable after the first publish.
            ->and($fresh->published_at->equalTo($project->published_at))->toBeTrue();
    });

    it('reconciles the tech tags on update (A2)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $php = projectSkillId('PHP');
        $go = projectSkillId('Go');
        $project = projectItem();

        // Seed the pivot through the dedicated model so the
        // `tech_tags_count` increment event fires (the belongsToMany
        // `attach()` fixture would bypass the repository path).
        \App\Features\Projects\Infra\Models\ProjectTechTagModel::query()->create([
            'project_id' => $project->id,
            'skill_id' => $php,
        ]);

        $this->put("/admin/projects/{$project->id}", projectPayload([
            'tech_tags' => [$go],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $project->fresh();
        expect($fresh->techTags()->pluck('skills.id')->all())->toBe([$go])
            ->and($fresh->tech_tags_count)->toBe(1);
    });

    it('reconciles the screenshots on update (A2)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem();

        Storage::disk('rustfs')->put('projects/removed.png', 'x');
        $removed = ProjectScreenshotModel::factory()->create([
            'project_id' => $project->id,
            'image_path' => 'projects/removed.png',
            'sort_order' => 0,
        ]);
        $kept = ProjectScreenshotModel::factory()->create([
            'project_id' => $project->id,
            'image_path' => 'projects/kept.png',
            'sort_order' => 1,
        ]);

        $this->put("/admin/projects/{$project->id}", projectPayload([
            'screenshots' => [
                ['id' => $kept->id, 'image' => 'projects/kept.png', 'alt_text' => ['en' => 'New alt'], 'title' => [], 'description' => []],
                ['image' => 'projects/new.png', 'alt_text' => [], 'title' => [], 'description' => []],
            ],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $screenshots = $project->fresh()->screenshots()->get();
        expect($screenshots)->toHaveCount(2)
            ->and($screenshots->firstWhere('id', $kept->id)->alt_text)->toBe(['en' => 'New alt'])
            ->and($screenshots->firstWhere('id', $kept->id)->sort_order)->toBe(0)
            ->and($screenshots->last()->image_path)->toBe('projects/new.png')
            ->and($screenshots->last()->sort_order)->toBe(1);

        // The absent screenshot row was deleted with its RustFS file.
        expect(ProjectScreenshotModel::find($removed->id))->toBeNull()
            ->and(Storage::disk('rustfs')->exists('projects/removed.png'))->toBeFalse();
    });

    it('deletes the replaced screenshot image file on an image change', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        Storage::disk('rustfs')->put('projects/old.png', 'x');
        $project = projectItem();
        $shot = ProjectScreenshotModel::factory()->create([
            'project_id' => $project->id,
            'image_path' => 'projects/old.png',
            'sort_order' => 0,
        ]);

        $this->put("/admin/projects/{$project->id}", projectPayload([
            'screenshots' => [
                ['id' => $shot->id, 'image' => 'projects/new.png', 'alt_text' => [], 'title' => [], 'description' => []],
            ],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Storage::disk('rustfs')->exists('projects/old.png'))->toBeFalse()
            ->and($shot->fresh()->image_path)->toBe('projects/new.png');
    });

    it('regenerates the slug from the changed EN title when the submitted slug matches the stored one (A3)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem(['slug' => 'payments-gateway']);

        $this->put("/admin/projects/{$project->id}", projectPayload([
            'slug' => 'payments-gateway',
            'title' => ['en' => 'Payments Hub', 'ru' => 'Хаб'],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($project->fresh()->slug)->toBe('payments-hub');

        // The old slug redirects to the new one.
        expect(DB::table('old_slugs')->where('old_slug', 'payments-gateway')->where('entity_type', 'project')->exists())->toBeTrue();
    });

    it('treats a changed submitted slug as a manual override (A3)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem(['slug' => 'payments-gateway']);

        $this->put("/admin/projects/{$project->id}", projectPayload([
            'slug' => 'payments-v2',
            'title' => ['en' => 'Payments Gateway', 'ru' => 'Шлюз'],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($project->fresh()->slug)->toBe('payments-v2');
    });

    it('rejects an update with a slug taken by another project', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        projectItem(['slug' => 'taken']);
        $project = projectItem();

        $this->put("/admin/projects/{$project->id}", projectPayload([
            'slug' => 'taken',
        ]))->assertRedirect()
            ->assertSessionHasErrors('slug');
    });

    it('soft-deletes, restores and force-deletes projects', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem();
        Storage::disk('rustfs')->put('projects/shot.png', 'x');
        ProjectScreenshotModel::factory()->create([
            'project_id' => $project->id,
            'image_path' => 'projects/shot.png',
        ]);

        $this->delete("/admin/projects/{$project->id}")->assertRedirect();
        expect(ProjectModel::withTrashed()->find($project->id)->deleted_at)->not->toBeNull();

        $this->patch("/admin/projects/{$project->id}/restore")->assertRedirect();
        expect(ProjectModel::find($project->id)->deleted_at)->toBeNull();

        // Force-delete: the RustFS files are removed and the slug
        // history is preserved.
        Storage::disk('rustfs')->assertExists('projects/shot.png');
        $this->delete("/admin/projects/{$project->id}/force")->assertRedirect();
        expect(ProjectModel::withTrashed()->find($project->id))->toBeNull()
            ->and(Storage::disk('rustfs')->exists('projects/shot.png'))->toBeFalse()
            ->and(DB::table('old_slugs')->where('old_slug', $project->slug)->where('entity_type', 'project')->exists())->toBeTrue()
            ->and(DB::table('project_screenshots')->where('project_id', $project->id)->exists())->toBeFalse()
            ->and(DB::table('project_translations')->where('project_id', $project->id)->exists())->toBeFalse();
    });

    it('restores soft-deleted projects', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $trashed = projectItem(['deleted_at' => now()]);

        $this->patch("/admin/projects/{$trashed->id}/restore")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(ProjectModel::find($trashed->id)->deleted_at)->toBeNull();
    });

    it('features with the max+1 default order and unfeatures preserving it', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $first = projectItem(['is_featured' => true, 'featured_sort_order' => 4]);
        $project = projectItem();

        $this->patch("/admin/projects/{$project->id}/feature")
            ->assertOk()
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.featured_sort_order', 5);

        expect($project->fresh()->featured_sort_order)->toBe(5);

        $this->patch("/admin/projects/{$project->id}/unfeature")
            ->assertOk()
            ->assertJsonPath('data.is_featured', false)
            ->assertJsonPath('data.featured_sort_order', 5);

        expect($project->fresh()->featured_sort_order)->toBe(5)
            ->and($first->fresh()->featured_sort_order)->toBe(4);
    });

    it('reorders the featured projects atomically', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $a = projectItem(['is_featured' => true, 'featured_sort_order' => 0]);
        $b = projectItem(['is_featured' => true, 'featured_sort_order' => 1]);

        $this->post('/admin/projects/reorder-featured', ['ids' => [$b->id, $a->id]])
            ->assertOk();

        expect($b->fresh()->featured_sort_order)->toBe(0)
            ->and($a->fresh()->featured_sort_order)->toBe(1);
    });

    it('publishes once (published_at immutable) and unpublishes', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem();

        $this->patch("/admin/projects/{$project->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $firstPublishedAt = $project->fresh()->published_at;

        $this->patch("/admin/projects/{$project->id}/unpublish")->assertOk();
        $this->patch("/admin/projects/{$project->id}/publish")->assertOk();

        expect($project->fresh()->published_at->equalTo($firstPublishedAt))->toBeTrue();
    });

    it('links the tech writeup only to published articles', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $project = projectItem();

        $publishedArticleId = DB::table('blog_articles')->insertGetId([
            'slug' => 'published-article', 'is_published' => true, 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->patch("/admin/projects/{$project->id}/tech-writeup", [
            'tech_writeup_article_id' => $publishedArticleId,
        ])->assertOk();

        expect($project->fresh()->tech_writeup_article_id)->toBe($publishedArticleId);

        // Null removes the link.
        $this->patch("/admin/projects/{$project->id}/tech-writeup", [
            'tech_writeup_article_id' => null,
        ])->assertOk();

        expect($project->fresh()->tech_writeup_article_id)->toBeNull();
    });

    it('attaches and detaches tags with the persisted count and the 409 on duplicates', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $php = projectSkillId('PHP');
        $go = projectSkillId('Go');
        $project = projectItem();

        $this->post("/admin/projects/{$project->id}/tags", ['skill_id' => $php])
            ->assertOk()
            ->assertJsonPath('data.tech_tags_count', 1);

        $this->post("/admin/projects/{$project->id}/tags", ['skill_id' => $php])
            ->assertStatus(409)
            ->assertJsonPath('message', "Skill [{$php}] is already attached to project [{$project->id}].");

        expect($project->fresh()->tech_tags_count)->toBe(1);

        $this->delete("/admin/projects/{$project->id}/tags/{$php}")
            ->assertOk()
            ->assertJsonPath('data.tech_tags_count', 0);

        $this->post("/admin/projects/{$project->id}/tags", ['skill_id' => $go])
            ->assertOk();

        expect($project->fresh()->tech_tags_count)->toBe(1);
    });

    it('applies the bulk publish/unpublish/delete/restore/force-delete actions', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $a = projectItem();
        $b = projectItem();

        $this->post('/admin/projects/bulk-publish', ['ids' => [$a->id, $b->id]])->assertOk();
        expect(ProjectModel::query()->where('is_published', true)->count())->toBe(2);

        $this->post('/admin/projects/bulk-unpublish', ['ids' => [$a->id]])->assertOk();
        expect($a->fresh()->is_published)->toBeFalse()
            ->and($b->fresh()->is_published)->toBeTrue();

        $this->post('/admin/projects/bulk-delete', ['ids' => [$a->id, $b->id]])->assertOk();
        expect(ProjectModel::withTrashed()->whereNotNull('deleted_at')->count())->toBe(2);

        $this->post('/admin/projects/bulk-restore', ['ids' => [$a->id, $b->id]])->assertOk();
        expect(ProjectModel::query()->count())->toBe(2);

        $this->post('/admin/projects/bulk-delete', ['ids' => [$a->id, $b->id]])->assertOk();
        $this->post('/admin/projects/bulk-force-delete', ['ids' => [$a->id, $b->id]])->assertOk();
        expect(ProjectModel::withTrashed()->count())->toBe(0);
    });

    it('invalidates the sitemap cache on published saves and form edits (B64)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $cache = static function (): bool {
            return Cache::has('sitemap:xml');
        };
        $prime = static function (): void {
            Cache::put('sitemap:xml', '<primed/>', 3600);
        };

        $prime();
        $project = projectItem(['is_published' => true, 'published_at' => now()]);
        expect($cache())->toBeFalse();

        // An unpublished project's form save still invalidates — the
        // translation sync rebuilds `search_vector` and invalidates the
        // sitemap unconditionally (seo.md:219 — translation changes are
        // reflected in lastmod).
        $project->update(['is_published' => false]);
        $prime();
        $this->put("/admin/projects/{$project->id}", projectPayload([
            'title' => ['en' => 'Renamed', 'ru' => 'Переименован'],
        ]))->assertRedirect();
        expect($cache())->toBeFalse();
    });

    it('invalidates the sitemap cache on unpublishes (B64)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $prime = static function (): void {
            Cache::put('sitemap:xml', '<primed/>', 3600);
        };
        $project = projectItem(['is_published' => true, 'published_at' => now()]);

        $prime();
        $this->patch("/admin/projects/{$project->id}/unpublish")->assertOk();

        expect(Cache::has('sitemap:xml'))->toBeFalse();
    });

    it('invalidates the sitemap cache on deletes (B64)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $prime = static function (): void {
            Cache::put('sitemap:xml', '<primed/>', 3600);
        };
        $published = projectItem(['is_published' => true, 'published_at' => now()]);

        $prime();
        $this->delete("/admin/projects/{$published->id}")->assertRedirect();

        expect(Cache::has('sitemap:xml'))->toBeFalse();
    });
});
