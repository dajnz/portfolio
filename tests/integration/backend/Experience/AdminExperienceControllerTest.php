<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Experience;

use App\Features\Experience\Infra\Models\TimelineItemModel;
use App\Features\Experience\Infra\Models\TimelineItemTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);

    // The continuity rules make the timeline state matter — items left
    // behind by a previous test in this file (LazilyRefreshDatabase
    // refreshes once per file) would act as predecessors/successors and
    // reject otherwise-valid dates. Each test starts with an empty
    // timeline (translations + the tech-tag pivot cascade).
    TimelineItemModel::query()->forceDelete();
});

/**
 * A null `ended_at` marks the ongoing position; there is no `is_current`
 * flag.
 *
 * @return array<string, mixed>
 */
function experiencePayload(array $overrides = []): array
{
    return array_merge([
        'position' => ['en' => 'Senior Developer', 'ru' => 'Старший разработчик'],
        'company_name' => ['en' => 'Acme Corp', 'ru' => 'ООО «Акме»'],
        'company_country' => ['en' => 'Germany', 'ru' => 'Германия'],
        'short_description' => ['en' => '**Led** the platform team.', 'ru' => 'Руководил командой платформы.'],
        'achievements' => [
            [
                'description' => ['en' => 'Cut deploy time by 40%', 'ru' => 'Сократил время деплоя на 40%'],
                'project_link' => 'https://example.com/work',
            ],
        ],
        'started_at' => '2020-01-15',
        'ended_at' => '2023-06-30',
        'is_published' => true,
        'include_in_resume' => true,
        'sort_order' => 0,
        'tech_tags' => [],
    ], $overrides);
}

function translatedItem(array $attributes = [], array $translations = []): TimelineItemModel
{
    $item = TimelineItemModel::factory()->create($attributes);

    $defaults = [
        'en' => ['position' => 'Engineer', 'company_name' => 'Example Ltd', 'company_country' => 'UK'],
        'ru' => ['position' => 'Инженер', 'company_name' => 'ООО «Пример»', 'company_country' => 'Россия'],
    ];

    foreach (array_merge($defaults, $translations) as $locale => $fields) {
        TimelineItemTranslationModel::factory()
            ->forLocale($locale)
            ->create(['item_id' => $item->id] + $fields);
    }

    return $item;
}

describe('AdminExperienceController', function (): void {
    it('requires authentication for the list', function (): void {
        $this->get('/admin/experience')->assertRedirect('/admin/login');
    });

    it('requires authentication for the form and mutations', function (): void {
        $this->get('/admin/experience/create')->assertRedirect('/admin/login');
        $this->get('/admin/experience/1/edit')->assertRedirect('/admin/login');
        $this->post('/admin/experience', experiencePayload())->assertRedirect('/admin/login');
        $this->put('/admin/experience/1', experiencePayload())->assertRedirect('/admin/login');
        $this->delete('/admin/experience/1')->assertRedirect('/admin/login');
        $this->patch('/admin/experience/1/restore')->assertRedirect('/admin/login');
        $this->delete('/admin/experience/1/force')->assertRedirect('/admin/login');
    });

    it('lists items with the current-locale fields and pagination meta', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $older = translatedItem(['started_at' => '2018-03-01']);
        $newer = translatedItem([
            'started_at' => '2022-05-01',
            'ended_at' => null,
            'is_published' => false,
            'include_in_resume' => false,
        ], [
            'en' => ['position' => 'Architect', 'company_name' => 'Nova AG'],
            'ru' => ['position' => 'Архитектор', 'company_name' => 'Нова АГ'],
        ]);

        $this->get('/admin/experience')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Experience/Admin/Pages/ExperienceListPage')
                ->has('items', 2)
                ->where('items.0.id', $newer->id)
                ->where('items.0.position', 'Architect')
                ->where('items.0.company_name', 'Nova AG')
                ->where('items.0.started_at', '2022-05-01')
                ->where('items.0.ended_at', null)
                ->where('items.0.is_published', false)
                ->where('items.0.include_in_resume', false)
                ->where('items.0.created_at', $newer->created_at->toIso8601String())
                ->where('items.1.id', $older->id)
                ->where('items.1.position', 'Engineer')
                ->where('items.1.company_name', 'Example Ltd')
                ->where('items.1.ended_at', null)
                ->where('meta.total', 2)
                ->where('meta.per_page', 12)
                ->where('meta.current_page', 1)
                ->where('meta.sort_column', 'started_at')
                ->where('meta.cursors.first.id', $newer->id)
                ->where('meta.cursors.last.id', $older->id));
    });

    it('hides trashed items by default and shows them with ?trashed=1', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $live = translatedItem();
        $trashed = translatedItem(['deleted_at' => now()]);

        $this->get('/admin/experience')
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $live->id)
                ->where('meta.total', 1));

        $this->get('/admin/experience?trashed=1')
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.id', $trashed->id)
                ->where('meta.total', 1));
    });

    it('searches by content across the per-locale columns', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        translatedItem(['started_at' => '2020-01-01'], [
            'en' => ['position' => 'Site Reliability Engineer', 'company_name' => 'Alpha'],
        ]);
        translatedItem(['started_at' => '2021-01-01'], [
            'en' => ['position' => 'DevOps', 'company_name' => 'Beta'],
            'ru' => ['position' => 'Девопс', 'company_name' => 'Бета'],
        ]);

        $this->get('/admin/experience?q=reliability')
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.position', 'Site Reliability Engineer')
                ->where('meta.total', 1));

        $this->get('/admin/experience?q=Бета')
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.company_name', 'Beta')
                ->where('meta.total', 1));
    });

    it('sorts by started_at in both directions', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $a = translatedItem(['started_at' => '2019-01-01']);
        $b = translatedItem(['started_at' => '2023-01-01']);

        $this->get('/admin/experience?sort_column=started_at&sort_direction=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.id', $b->id)
                ->where('items.1.id', $a->id)
                ->where('meta.sort_column', 'started_at')
                ->where('meta.sort_direction', 'desc'));

        $this->get('/admin/experience?sort_column=started_at&sort_direction=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.id', $a->id)
                ->where('items.1.id', $b->id)
                ->where('meta.sort_direction', 'asc'));
    });

    it('serves the create form with skills and projects', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = \App\Features\Shared\Infra\Models\SkillGroupModel::factory()->create();
        SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'PHP', 'icon' => 'devicon:php']);
        $projectId = DB::table('projects')->insertGetId([
            'slug' => 'payments',
            'is_published' => true,
            'featured_sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('project_translations')->insert([
            ['project_id' => $projectId, 'locale' => 'en', 'title' => 'Payments Gateway', 'brief_description' => 'Gateway'],
        ]);

        $this->get('/admin/experience/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Experience/Admin/Pages/ExperienceFormPage')
                ->where('item', null)
                ->has('skills', 1)
                ->where('skills.0.name', 'PHP')
                ->has('projects', 1)
                ->where('projects.0.id', $projectId)
                ->where('projects.0.title', 'Payments Gateway')
                ->where('projects.0.slug', 'payments'));
    });

    it('stores an item with per-locale translations, achievements and tech tags', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = \App\Features\Shared\Infra\Models\SkillGroupModel::factory()->create();
        $php = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'PHP']);

        $this->post('/admin/experience', experiencePayload(['tech_tags' => [$php->id]]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = TimelineItemModel::query()->first();
        expect($item)->not->toBeNull()
            ->and($item->started_at->format('Y-m-d'))->toBe('2020-01-15')
            ->and($item->ended_at->format('Y-m-d'))->toBe('2023-06-30')
            ->and($item->is_published)->toBeTrue()
            ->and($item->include_in_resume)->toBeTrue();

        $translations = $item->translations()->get()->keyBy('locale');
        expect($translations->get('en')->position)->toBe('Senior Developer')
            ->and($translations->get('en')->company_name)->toBe('Acme Corp')
            ->and($translations->get('en')->company_country)->toBe('Germany')
            ->and($translations->get('en')->short_description)->toBe('**Led** the platform team.')
            ->and($translations->get('en')->achievements)->toBe([
                ['description' => 'Cut deploy time by 40%', 'project_link' => 'https://example.com/work'],
            ])
            ->and($translations->get('ru')->position)->toBe('Старший разработчик');

        expect(DB::table('experience_item_tech_tags')
            ->where('experience_item_id', $item->id)
            ->pluck('skill_id')->all())->toBe([$php->id]);
    });

    it('treats a null ended_at as the ongoing position (no is_current flag)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/experience', experiencePayload([
            'ended_at' => null,
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(TimelineItemModel::query()->first()->ended_at)->toBeNull();
    });

    it('stores only the achievements with a description per locale', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/experience', experiencePayload([
            'achievements' => [
                ['description' => ['en' => 'English only', 'ru' => null], 'project_link' => null],
                ['description' => ['en' => 'Both locales', 'ru' => 'На обоих языках'], 'project_link' => 'https://example.com/2'],
            ],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $translations = TimelineItemModel::query()->first()->translations()->get()->keyBy('locale');
        expect($translations->get('en')->achievements)->toBe([
            ['description' => 'English only', 'project_link' => null],
            ['description' => 'Both locales', 'project_link' => 'https://example.com/2'],
        ])
            ->and($translations->get('ru')->achievements)->toBe([
                ['description' => 'На обоих языках', 'project_link' => 'https://example.com/2'],
            ]);
    });

    it('validates the required fields and date rules', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/experience', [
            'short_description' => [],
            'started_at' => '2023-06-30',
            'ended_at' => '2020-01-15',
        ])->assertRedirect()
            ->assertSessionHasErrors(['short_description.en', 'short_description.ru', 'ended_at']);
    });

    it('requires position per locale and the company pair all-or-none', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/experience', experiencePayload([
            'position' => ['en' => '', 'ru' => null],
        ]))->assertRedirect()
            ->assertSessionHasErrors(['position.en', 'position.ru']);

        $this->post('/admin/experience', experiencePayload([
            'company_name' => ['en' => 'Acme Corp', 'ru' => 'ООО «Акме»'],
            'company_country' => ['en' => null, 'ru' => null],
        ]))->assertRedirect()
            ->assertSessionHasErrors(['company_name.en', 'company_name.ru']);

        // Position alone (company pair empty) passes — the company is
        // optional.
        $this->post('/admin/experience', experiencePayload([
            'company_name' => ['en' => '', 'ru' => null],
            'company_country' => ['en' => null, 'ru' => null],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(TimelineItemModel::query()->first()->translations()->where('locale', 'en')->first()->company_name)->toBe('');
    });

    it('rejects ended_at equal to started_at', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/experience', experiencePayload([
            'started_at' => '2023-06-30',
            'ended_at' => '2023-06-30',
        ]))->assertRedirect()
            ->assertSessionHasErrors('ended_at');
    });

    it('auto-fills the previous item end date when appending after an ongoing item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $ongoing = translatedItem(['started_at' => '2020-01-01', 'ended_at' => null]);

        $this->post('/admin/experience', experiencePayload([
            'started_at' => '2021-01-01',
            'ended_at' => '2022-01-01',
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($ongoing->fresh()->ended_at?->format('Y-m-d'))->toBe('2021-01-01');
    });

    it('rejects a period overlapping the previous item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        translatedItem(['started_at' => '2020-01-01', 'ended_at' => '2020-06-30']);

        $this->post('/admin/experience', experiencePayload([
            'started_at' => '2020-03-01',
            'ended_at' => '2020-12-31',
        ]))->assertRedirect()
            ->assertSessionHasErrors('started_at');
    });

    it('rejects an item starting on the same day as an ongoing item (ADR-0024)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        // The reported bug: an ongoing "2026-03-01 — Present" item let a
        // second "2026-03-01 — 2026-03-22" item through — the same-start
        // predecessor was skipped (strict < comparison) and its end date
        // was never auto-filled. Same-start items chain by id: the new
        // item follows the ongoing one, which cannot be closed on its own
        // start day (ended > started) — an overlap.
        translatedItem(['started_at' => '2026-03-01', 'ended_at' => null]);

        $this->post('/admin/experience', experiencePayload([
            'started_at' => '2026-03-01',
            'ended_at' => '2026-03-22',
        ]))->assertRedirect()
            ->assertSessionHasErrors('started_at');

        expect(TimelineItemModel::query()->first()->ended_at)->toBeNull();
    });

    it('rejects a period overlapping the next item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        translatedItem(['started_at' => '2021-01-01', 'ended_at' => '2022-06-30']);

        $this->post('/admin/experience', experiencePayload([
            'started_at' => '2020-01-01',
            'ended_at' => '2021-06-01',
        ]))->assertRedirect()
            ->assertSessionHasErrors('ended_at');
    });

    it('rejects an ongoing item that is not the chronologically last one', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        translatedItem(['started_at' => '2021-01-01', 'ended_at' => '2022-01-01']);

        $this->post('/admin/experience', experiencePayload([
            'started_at' => '2020-06-01',
            'ended_at' => null,
        ]))->assertRedirect()
            ->assertSessionHasErrors('ended_at');
    });

    it('allows an ongoing item as the last one and closes it when a newer item is added', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        translatedItem(['started_at' => '2020-01-01', 'ended_at' => '2020-12-31']);

        $this->post('/admin/experience', experiencePayload([
            'started_at' => '2021-01-01',
            'ended_at' => null,
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $ongoing = TimelineItemModel::query()->orderByDesc('started_at')->first();
        expect($ongoing->ended_at)->toBeNull();

        // Appending after the ongoing item auto-fills its end date.
        $this->post('/admin/experience', experiencePayload([
            'started_at' => '2023-01-01',
            'ended_at' => null,
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($ongoing->fresh()->ended_at?->format('Y-m-d'))->toBe('2023-01-01');
    });

    it('rejects an update that moves an item onto its predecessor', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        translatedItem(['started_at' => '2020-01-01', 'ended_at' => '2020-12-31']);
        $item = translatedItem(['started_at' => '2021-01-01', 'ended_at' => '2022-01-01']);

        $this->put("/admin/experience/{$item->id}", experiencePayload([
            'started_at' => '2020-06-01',
            'ended_at' => '2022-01-01',
        ]))->assertRedirect()
            ->assertSessionHasErrors('started_at');
    });

    it('auto-fills the previous item when an update moves an item after the ongoing one', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $ongoing = translatedItem(['started_at' => '2020-01-01', 'ended_at' => null]);
        $item = translatedItem(['started_at' => '2019-01-01', 'ended_at' => '2019-12-31']);

        $this->put("/admin/experience/{$item->id}", experiencePayload([
            'started_at' => '2021-01-01',
            'ended_at' => '2022-01-01',
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($ongoing->fresh()->ended_at?->format('Y-m-d'))->toBe('2021-01-01');
    });

    it('rejects the project_id / external_project_name exclusivity violation', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $projectId = DB::table('projects')->insertGetId([
            'slug' => 'payments', 'is_published' => true, 'featured_sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->post('/admin/experience', experiencePayload([
            'project_id' => $projectId,
            'external_project_name' => 'External Project',
        ]))->assertRedirect()
            ->assertSessionHasErrors(['project_id', 'external_project_name']);
    });

    it('rejects tech tags that do not exist and non-positive sort orders', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/experience', experiencePayload([
            'tech_tags' => [999],
            'sort_order' => -1,
        ]))->assertRedirect()
            ->assertSessionHasErrors(['tech_tags.0', 'sort_order']);
    });

    it('validates short_description against its plain-text length after Markdown stripping', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        // 500+ raw characters that strip to ~380 plain characters (links
        // and emphasis removed) — passes the length check...
        $linkHeavy = '[Link text](https://example.com/a-very-long-destination-path) '.
            '**bold** _italic_ `code` '.str_repeat('plain ', 70);
        $long = str_repeat('plain text with markup **bold** and [links](https://example.com) ', 50);

        $this->post('/admin/experience', experiencePayload([
            'short_description' => ['en' => $linkHeavy, 'ru' => 'Краткое описание'],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        // ...while 500+ plain characters without any markup fail it.
        $this->post('/admin/experience', experiencePayload([
            'short_description' => ['en' => $long, 'ru' => 'Краткое описание'],
        ]))->assertRedirect()
            ->assertSessionHasErrors('short_description.en');
    });

    it('serves the edit form with per-locale maps and the merged achievements', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $item = translatedItem(['started_at' => '2021-04-01', 'ended_at' => null, 'is_published' => false], [
            'en' => ['position' => 'Engineer', 'company_name' => 'Example Ltd', 'short_description' => 'Short en', 'achievements' => [
                ['description' => 'English achievement', 'project_link' => 'https://example.com/1'],
            ]],
            'ru' => ['position' => 'Инженер', 'company_name' => 'ООО «Пример»', 'short_description' => 'Short ru', 'achievements' => [
                ['description' => 'Русское достижение', 'project_link' => null],
            ]],
        ]);

        $this->get("/admin/experience/{$item->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Experience/Admin/Pages/ExperienceFormPage')
                ->where('item.id', $item->id)
                ->where('item.position.en', 'Engineer')
                ->where('item.position.ru', 'Инженер')
                ->where('item.company_name.en', 'Example Ltd')
                ->where('item.short_description.en', 'Short en')
                ->where('item.started_at', '2021-04-01')
                ->where('item.ended_at', null)
                ->where('item.is_published', false)
                ->where('item.include_in_resume', true)
                ->where('item.achievements.0.description.en', 'English achievement')
                ->where('item.achievements.0.description.ru', 'Русское достижение')
                ->where('item.achievements.0.project_link', 'https://example.com/1'));
    });

    it('updates an item and reconciles translations and the tech-tag pivot', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = \App\Features\Shared\Infra\Models\SkillGroupModel::factory()->create();
        $php = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'PHP']);
        $go = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'Go']);

        $item = translatedItem(['started_at' => '2020-01-01', 'ended_at' => '2020-12-31']);
        $item->techTags()->attach([$php->id]);

        $this->put("/admin/experience/{$item->id}", experiencePayload([
            'position' => ['en' => 'Staff Engineer', 'ru' => 'Ведущий инженер'],
            'company_name' => ['en' => 'Acme Corp', 'ru' => 'ООО «Акме»'],
            'started_at' => '2024-01-01',
            'ended_at' => null,
            'tech_tags' => [$go->id],
        ]))->assertRedirect()
            ->assertSessionHasNoErrors();

        $fresh = $item->fresh();
        expect($fresh->started_at->format('Y-m-d'))->toBe('2024-01-01')
            ->and($fresh->ended_at)->toBeNull()
            ->and($fresh->translations()->where('locale', 'en')->first()->position)->toBe('Staff Engineer')
            ->and($fresh->techTags()->pluck('skills.id')->all())->toBe([$go->id]);
    });

    it('soft-deletes, restores and force-deletes items', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = \App\Features\Shared\Infra\Models\SkillGroupModel::factory()->create();
        $php = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'PHP']);
        $item = translatedItem();
        $item->techTags()->attach([$php->id]);

        $this->delete("/admin/experience/{$item->id}")->assertRedirect();
        expect(TimelineItemModel::withTrashed()->find($item->id)->deleted_at)->not->toBeNull();

        $this->get('/admin/experience')->assertInertia(fn (Assert $page) => $page->where('meta.total', 0));
        $this->get('/admin/experience?trashed=1')->assertInertia(fn (Assert $page) => $page->where('meta.total', 1));

        $this->patch("/admin/experience/{$item->id}/restore")->assertRedirect();
        expect(TimelineItemModel::find($item->id)->deleted_at)->toBeNull();

        $this->delete("/admin/experience/{$item->id}/force")->assertRedirect();
        expect(TimelineItemModel::withTrashed()->find($item->id))->toBeNull()
            ->and(DB::table('experience_item_tech_tags')->where('experience_item_id', $item->id)->exists())->toBeFalse()
            ->and(DB::table('experience_timeline_item_translations')->where('item_id', $item->id)->exists())->toBeFalse();
    });

    it('invalidates the sitemap cache on published-item saves and deletes (ADR-0022)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $cache = static function (): bool {
            return Cache::has('sitemap:xml');
        };
        $prime = static function (): void {
            Cache::put('sitemap:xml', '<primed/>', 3600);
        };

        // Explicit, non-overlapping periods — the continuity rules reject
        // same-period items.
        $prime();
        $item = translatedItem(['is_published' => true, 'started_at' => '2020-01-01', 'ended_at' => '2021-06-30']);
        expect($cache())->toBeFalse();

        $prime();
        $this->put("/admin/experience/{$item->id}", experiencePayload([
            'is_published' => false,
            'started_at' => '2020-01-15',
            'ended_at' => '2021-06-30',
        ]))->assertRedirect();
        expect($cache())->toBeFalse();

        $prime();
        $this->put("/admin/experience/{$item->id}", experiencePayload([
            'is_published' => false,
            'started_at' => '2020-01-15',
            'ended_at' => '2021-06-30',
        ]))->assertRedirect();
        expect($cache())->toBeTrue();

        $second = translatedItem(['is_published' => true, 'started_at' => '2022-01-01', 'ended_at' => '2023-01-01']);
        $prime();
        $this->delete("/admin/experience/{$second->id}")->assertRedirect();
        expect($cache())->toBeFalse();
    });
});
