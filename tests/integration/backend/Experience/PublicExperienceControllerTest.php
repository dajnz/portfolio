<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Experience;

use App\Features\Experience\Infra\Models\TimelineItemModel;
use App\Features\Experience\Infra\Models\TimelineItemTranslationModel;
use App\Features\Settings\Infra\Models\SettingModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use JsonException;

function publicItem(array $attributes = [], array $translations = []): TimelineItemModel
{
    $item = TimelineItemModel::factory()->published()->create($attributes);

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

function seededProject(string $slug, string $localeTitle, string $enTitle = ''): int
{
    $id = DB::table('projects')->insertGetId([
        'slug' => $slug,
        'is_published' => true,
        'featured_sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('project_translations')->insert([
        ['project_id' => $id, 'locale' => 'en', 'title' => $enTitle ?: $localeTitle, 'brief_description' => 'Brief'],
        ['project_id' => $id, 'locale' => 'ru', 'title' => $localeTitle, 'brief_description' => 'Кратко'],
    ]);

    return $id;
}

/** Decodes the opaque next_cursor into its `{id, v}` parts. */
function decodedCursor(string $cursor): array
{
    $json = base64_decode(strtr($cursor, '-_', '+/'), true);

    try {
        return json_decode((string) $json, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
}

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);

    // 2 items per page makes the cursor pagination assertions cheap.
    SettingModel::query()->updateOrCreate(
        ['key' => 'experience_items_per_page'],
        ['value' => '2', 'type' => 'integer'],
    );
});

describe('PublicExperienceController', function (): void {
    it('serves the timeline ordered by started_at desc with seo props', function (): void {
        $older = publicItem(['started_at' => '2015-01-01', 'ended_at' => '2019-12-31']);
        $newer = publicItem([
            'started_at' => '2024-05-10',
            'ended_at' => null,
            'external_project_name' => 'Side Project',
            'external_project_url' => 'https://example.com/side-project',
        ], [
            'en' => ['position' => 'Architect', 'company_name' => 'Nova AG', 'company_country' => 'Switzerland'],
        ]);

        $this->get('/experience')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Experience/Public/Pages/ExperienceTimelinePage')
                ->has('items', 2)
                ->where('items.0.id', $newer->id)
                ->where('items.0.position', 'Architect')
                ->where('items.0.company_name', 'Nova AG')
                ->where('items.0.company_country', 'Switzerland')
                ->where('items.0.started_at', '2024-05-10')
                ->where('items.0.ended_at', null)
                ->where('items.0.is_current', true)
                ->where('items.0.project_id', null)
                ->where('items.0.project_name', 'Side Project')
                ->where('items.0.project_slug', null)
                ->where('items.0.project_url', 'https://example.com/side-project')
                ->where('items.1.id', $older->id)
                ->where('items.1.is_current', false)
                // 2 items = a full batch at the seeded per_page (2):
                // a next cursor is offered even though the list is done.
                ->where('has_more', true)
                ->where('next_cursor', fn (mixed $cursor) => is_string($cursor) && $cursor !== '')
                ->where('has_projects', false)
                ->where('seo.title', 'Experience — ')
                ->where('seo.description', 'Professional experience and career timeline.')
                ->where('seo.canonical_url', url('/experience'))
                ->where('seo.og_url', url('/experience'))
                ->where('seo.og_type', 'website'));
    });

    it('excludes unpublished and trashed items', function (): void {
        publicItem(['started_at' => '2020-01-01']);
        publicItem(['started_at' => '2021-01-01', 'is_published' => false]);
        publicItem(['started_at' => '2022-01-01', 'deleted_at' => now()]);

        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 1)
                ->where('items.0.started_at', '2020-01-01'));
    });

    it('reports has_projects only for a published, non-trashed project', function (): void {
        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page->where('has_projects', false));

        seededProject('payments', 'Payments Gateway');
        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page->where('has_projects', true));

        DB::table('projects')->update(['is_published' => false]);
        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page->where('has_projects', false));

        DB::table('projects')->update(['is_published' => true, 'deleted_at' => now()]);
        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page->where('has_projects', false));
    });

    it('interpolates the locale-resolved about name into the seo title', function (): void {
        DB::table('about_info')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('about_info_translations')->insert([
            ['about_info_id' => 1, 'locale' => 'en', 'name' => 'Alex Kovalev', 'title' => 'Dev', 'short_pitch' => 'Pitch'],
            ['about_info_id' => 1, 'locale' => 'ru', 'name' => 'Алекс Ковалёв', 'title' => 'Разработчик', 'short_pitch' => 'Питч'],
        ]);

        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page->where('seo.title', 'Experience — Alex Kovalev'));

        $this->get('/ru/experience')
            ->assertInertia(fn (Assert $page) => $page->where('seo.title', 'Опыт работы — Алекс Ковалёв'));
    });

    it('resolves the linked project name and slug per locale', function (): void {
        $projectId = seededProject('payments', 'Платёжный шлюз', 'Payments Gateway');
        publicItem(['started_at' => '2020-01-01', 'project_id' => $projectId]);

        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.project_id', $projectId)
                ->where('items.0.project_name', 'Payments Gateway')
                ->where('items.0.project_slug', 'payments')
                ->where('items.0.project_url', null));

        $this->get('/ru/experience')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.project_name', 'Платёжный шлюз')
                ->where('items.0.project_slug', 'payments'));
    });

    it('renders the tech tags as the deduplicated union of own and project tags', function (): void {
        $group = SkillGroupModel::factory()->create();
        $php = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'PHP', 'icon' => 'devicon:php']);
        $go = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'Go', 'icon' => 'devicon:go']);
        $vue = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'Vue', 'icon' => 'devicon:vuejs']);

        $projectId = seededProject('gateway', 'Gateway');
        DB::table('project_tech_tags')->insert([
            ['project_id' => $projectId, 'skill_id' => $php->id],
            ['project_id' => $projectId, 'skill_id' => $go->id],
        ]);

        $item = publicItem(['started_at' => '2020-01-01', 'project_id' => $projectId]);
        $item->techTags()->attach([$php->id, $vue->id]);

        // Own tags first (in the repository's display order — sort_order,
        // then id: PHP, Vue), then the project's tags not already present
        // (Go). The PHP duplication collapses into one chip.
        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page
                ->has('items.0.tech_tags', 3)
                ->where('items.0.tech_tags.0.id', $php->id)
                ->where('items.0.tech_tags.0.name', 'PHP')
                ->where('items.0.tech_tags.0.icon', 'devicon:php')
                ->where('items.0.tech_tags.1.id', $vue->id)
                ->where('items.0.tech_tags.2.id', $go->id));
    });

    it('serves per-locale achievements', function (): void {
        publicItem(['started_at' => '2020-01-01'], [
            'en' => [
                'short_description' => 'Led the **deploy** pipeline.',
                'achievements' => [
                    ['description' => 'Cut **deploy** time by [40%](https://example.com/metrics)'],
                ],
            ],
            'ru' => ['achievements' => [
                ['description' => 'Сократил время деплоя'],
            ]],
        ]);

        $this->get('/experience')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.short_description', fn (mixed $value): bool => $value === "<p>Led the <strong>deploy</strong> pipeline.</p>\n")
                ->where('items.0.achievements.0.description', 'Cut <strong>deploy</strong> time by <a href="https://example.com/metrics">40%</a>')
                ->missing('items.0.achievements.0.project_link'));

        $this->get('/ru/experience')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.achievements.0.description', 'Сократил время деплоя'));
    });

    it('falls back to the en translation when the requested locale is missing', function (): void {
        publicItem(['started_at' => '2020-01-01']);

        // Item 2 carries only an en row — the ru request must fall back
        // to it.
        $item = TimelineItemModel::factory()->published()->create(['started_at' => '2021-01-01']);
        TimelineItemTranslationModel::factory()->forLocale('en')->create([
            'item_id' => $item->id,
            'position' => 'Only-English Role',
            'company_name' => 'EN Corp',
            'company_country' => 'US',
            'short_description' => 'Fallback text',
            'achievements' => null,
        ]);

        $this->get('/ru/experience')
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.0.position', 'Only-English Role')
                ->where('items.0.company_name', 'EN Corp')
                ->where('items.0.company_country', 'US')
                ->where('items.0.short_description', fn (mixed $value): bool => is_string($value) && str_contains($value, 'Fallback text')));
    });

    it('paginates with the opaque cursor and stops at exhaustion', function (): void {
        publicItem(['started_at' => '2024-01-01']);
        publicItem(['started_at' => '2023-01-01']);
        publicItem(['started_at' => '2022-01-01']);

        $response = $this->get('/experience');
        $response->assertInertia(fn (Assert $page) => $page
            ->has('items', 2)
            ->where('items.0.started_at', '2024-01-01')
            ->where('items.1.started_at', '2023-01-01')
            ->where('has_more', true)
            ->where('next_cursor', fn (mixed $cursor) => is_string($cursor) && $cursor !== ''));

        $cursor = decodedCursor((string) $response->inertiaProps('next_cursor'));
        expect($cursor['id'])->toBeInt()
            ->and($cursor['v'])->toBe('2023-01-01');

        // Load More — the response is a superset (the reconstructed view
        // + the next batch): all 3 items, and the final batch is
        // incomplete (1 < 2) so there is no further cursor.
        $this->get('/experience?cursor='.$response->inertiaProps('next_cursor'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 3)
                ->where('items.2.started_at', '2022-01-01')
                ->where('has_more', false)
                ->where('next_cursor', null));
    });

    it('restores the full scroll state on reload with a cursor', function (): void {
        publicItem(['started_at' => '2024-01-01']);
        publicItem(['started_at' => '2023-01-01']);
        publicItem(['started_at' => '2022-01-01']);
        publicItem(['started_at' => '2021-01-01']);

        // The user loaded batch 1 + 2; the URL cursor is batch 2's last item.
        $batch1 = $this->get('/experience')->inertiaProps('next_cursor');
        $batch2 = $this->get('/experience?cursor='.$batch1)->inertiaProps('next_cursor');
        expect(decodedCursor((string) $batch2)['v'])->toBe('2021-01-01');

        // Reload restores the 4-item view; the batch beyond the cursor
        // is empty — the list is exhausted, no Load More remains.
        $this->get('/experience?cursor='.$batch2)
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 4)
                ->where('items.0.started_at', '2024-01-01')
                ->where('items.3.started_at', '2021-01-01')
                ->where('has_more', false)
                ->where('next_cursor', null));
    });

    it('ignores a malformed cursor and serves the initial batch', function (): void {
        publicItem(['started_at' => '2024-01-01']);
        publicItem(['started_at' => '2023-01-01']);
        publicItem(['started_at' => '2022-01-01']);

        $this->get('/experience?cursor=not-base64%%%')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 2)
                ->where('has_more', true));

        // Valid base64 but not the expected shape.
        $this->get('/experience?cursor='.rtrim(strtr(base64_encode('garbage'), '+/', '-_'), '='))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('items', 2));
    });

    it('serves an empty timeline state', function (): void {
        $this->get('/experience')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('items', 0)
                ->where('has_more', false)
                ->where('next_cursor', null));
    });
});
