<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Portfolio;

use App\Features\Portfolio\Infra\Models\AboutInfoModel;
use App\Features\Portfolio\Infra\Models\AboutInfoTranslationModel;
use App\Features\Portfolio\Infra\Models\ContactModel;
use App\Features\Portfolio\Infra\Models\ContactTranslationModel;
use App\Features\Portfolio\Infra\Models\FeedbackModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Tests\Support\Traits\InteractsWithAdmin::class);

/**
 * Seeds a full landing dataset (about, skills, contacts, featured
 * projects) for the landing page tests.
 */
function seedLandingDataset(): void
{
    AboutInfoModel::factory()->create([
        'id' => 1,
        'photo' => 'about/photo.jpg',
        'photo_alt' => ['en' => 'Portrait', 'ru' => 'Портрет'],
        'availability_status' => 'available',
        'work_type' => 'hybrid',
        'show_location' => true,
        'timezone' => 'Europe/Berlin',
        'relocation_readiness' => true,
    ]);
    AboutInfoTranslationModel::query()->insert([
        ['about_info_id' => 1, 'locale' => 'en', 'name' => 'Alex Kovalev', 'title' => 'Full-Stack Developer', 'short_pitch' => 'I build fast web apps.', 'location' => 'Berlin'],
        ['about_info_id' => 1, 'locale' => 'ru', 'name' => 'Алекс Ковалев', 'title' => 'Фулстек-разработчик', 'short_pitch' => 'Я создаю быстрые веб-приложения.', 'location' => 'Берлин'],
    ]);

    $group = SkillGroupModel::factory()->create(['sort_order' => 0, 'icon' => 'heroicons:server']);
    SkillGroupTranslationModel::factory()->create(['skill_group_id' => $group->id, 'locale' => 'en', 'name' => 'Backend']);
    SkillModel::factory()->create([
        'skill_group_id' => $group->id,
        'name' => 'Laravel',
        'icon' => 'devicon:laravel',
        'proficiency' => 5,
        'sort_order' => 0,
    ]);

    $contact = ContactModel::factory()->create([
        'value' => 'octocat',
        'icon' => 'simple-icons:github',
        'url' => 'https://github.com/alex',
        'sort_order' => 0,
    ]);
    ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $contact->id, 'name' => 'GitHub']);
    ContactTranslationModel::factory()->forLocale('ru')->create(['contact_id' => $contact->id, 'name' => 'Гитхаб']);

    seedFeaturedProjects(2);
}

/**
 * Creates published projects; the first `$featured` are also featured.
 */
function seedFeaturedProjects(int $featured): void
{
    $count = 5;
    $ids = [];
    for ($i = 1; $i <= $count; $i++) {
        $ids[$i] = DB::table('projects')->insertGetId([
            'slug' => "project-$i",
            'preview_image' => null,
            'preview_image_alt' => null,
            'github_url' => null,
            'live_demo_url' => null,
            'tech_writeup_article_id' => null,
            'is_featured' => $i <= $featured,
            'featured_sort_order' => $i <= $featured ? $count - $i : null,
            'is_published' => true,
            'published_at' => now(),
            'tech_tags_count' => 0,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    foreach ($ids as $i => $projectId) {
        DB::table('project_translations')->insert([
            'project_id' => $projectId,
            'locale' => 'en',
            'title' => "Project $i",
            'brief_description' => "Brief $i",
            'full_description' => null,
            'problem' => null,
            'solution' => null,
            'search_vector' => null,
        ]);
    }

    DB::table('projects')->where('id', $ids[$count])->update(['deleted_at' => now()]);
}

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

describe('LandingController', function (): void {
    it('renders the landing page with all public props', function (): void {
        seedLandingDataset();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Public/Pages/LandingPage')
                ->where('locale', 'en')
                ->where('theme', 'light')
                ->where('auth.user', null)
                ->where('about.name', 'Alex Kovalev')
                ->where('about.title', 'Full-Stack Developer')
                ->where('about.short_pitch', 'I build fast web apps.')
                ->where('about.photo', 'about/photo.jpg')
                ->where('about.photo_alt.en', 'Portrait')
                ->where('about.location', 'Berlin')
                ->where('about.show_location', true)
                ->where('about.timezone', 'Europe/Berlin')
                ->where('about.availability_status', 'available')
                ->where('about.work_type', 'hybrid')
                ->where('about.relocation_readiness', true)
                ->has('skills', 1)
                ->where('skills.0.name', 'Backend')
                ->where('skills.0.icon', 'heroicons:server')
                ->where('skills.0.skills.0.name', 'Laravel')
                ->where('skills.0.skills.0.proficiency', 5)
                ->has('featured_projects', 2)
                ->where('featured_projects.0.title', 'Project 2')
                ->where('featured_projects.1.title', 'Project 1')
                ->where('featured_projects.0.tech_tags', [])
                ->where('has_more', true)
                ->has('contacts', 1)
                ->where('contacts.0.name', 'GitHub')
                ->where('contacts.0.value', 'octocat')
                ->where('contacts.0.icon', 'simple-icons:github')
                ->where('contacts.0.url', 'https://github.com/alex')
                ->where('seo.title', 'Alex Kovalev — Full-Stack Developer')
                ->where('seo.description', 'I build fast web apps.')
                ->where('seo.og_type', 'website')
                ->has('seo.json_ld', 2)
                ->where('seo.json_ld.0.@type', 'Person')
                ->where('seo.json_ld.1.@type', 'WebSite')
                ->where('console_greeting.greeting', 'Hey there! Welcome to my portfolio.')
                ->where('console_greeting.name', 'Alex Kovalev')
                ->where('console_greeting.position', 'Full-Stack Developer')
                ->where('console_greeting.contacts.0.label', 'GitHub')
                ->where('console_greeting.contacts.0.value', 'octocat'));
    });

    it('resolves the props per the {locale} path param', function (): void {
        seedLandingDataset();

        $this->get('/ru')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ru')
                ->where('about.name', 'Алекс Ковалев')
                ->where('about.location', 'Берлин')
                ->where('contacts.0.name', 'Гитхаб')
                ->where('seo.title', 'Алекс Ковалев — Fullstack-разработчик'));
    });

    it('embeds the session-derived honeypot field name in the landing props', function (): void {
        seedLandingDataset();

        $response = $this->get('/');

        $expected = substr(
            hash_hmac('sha256', 'honeypot_'.session()->getId(), (string) config('app.key')),
            0,
            32,
        );

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('feedback_honeypot_name', $expected)
                ->where('feedback_honeypot_name', fn (string $name) => preg_match('/^[0-9a-f]{32}$/', $name) === 1));
    });

    it('rejects an unknown locale prefix with 404', function (): void {
        $this->get('/fr')->assertNotFound();
    });

    it('tolerates an empty dataset', function (): void {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('about', null)
                ->where('skills', [])
                ->where('featured_projects', [])
                ->where('has_more', false)
                ->where('contacts', [])
                ->where('seo.title', ' — Full-Stack Developer'));
    });
});

describe('DashboardController', function (): void {
    it('requires authentication', function (): void {
        $this->get('/admin')->assertRedirect('/admin/login');
    });

    it('renders the stats per B32', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $publishedId = DB::table('blog_articles')->insertGetId([
            'slug' => 'published', 'is_published' => true, 'is_featured' => false,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $unpublishedId = DB::table('blog_articles')->insertGetId([
            'slug' => 'unpublished', 'is_published' => false, 'is_featured' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $trashedId = DB::table('blog_articles')->insertGetId([
            'slug' => 'trashed', 'is_published' => true, 'is_featured' => false,
            'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('article_views')->insert([
            ['article_id' => $publishedId, 'views' => 10],
            ['article_id' => $unpublishedId, 'views' => 7],
            ['article_id' => $trashedId, 'views' => 3],
        ]);

        DB::table('projects')->insert([
            ['slug' => 'p1', 'is_published' => true, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now(), 'tech_tags_count' => 0],
            ['slug' => 'p2', 'is_published' => false, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now(), 'tech_tags_count' => 0],
            ['slug' => 'p3', 'is_published' => true, 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'tech_tags_count' => 0],
        ]);

        $this->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/DashboardPage')
                ->where('stats.total_views', 20)
                ->where('stats.blog_views', 10)
                ->where('stats.projects_count', 2)
                ->where('stats.articles_count', 2));
    });
});

describe('AboutController', function (): void {
    it('requires authentication for the edit page', function (): void {
        $this->get('/admin/about')->assertRedirect('/admin/login');
    });

    it('renders the edit form with per-locale maps', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        AboutInfoModel::factory()->create(['id' => 1]);
        AboutInfoTranslationModel::query()->insert([
            ['about_info_id' => 1, 'locale' => 'en', 'name' => 'Alex', 'title' => 'Dev', 'short_pitch' => 'Pitch.', 'location' => 'Berlin'],
            ['about_info_id' => 1, 'locale' => 'ru', 'name' => 'Алекс', 'title' => 'Разработчик', 'short_pitch' => 'Описание.', 'location' => null],
        ]);

        $this->get('/admin/about')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/AboutEditPage')
                ->where('about.name.en', 'Alex')
                ->where('about.name.ru', 'Алекс')
                ->where('about.title.en', 'Dev')
                ->where('about.short_pitch.ru', 'Описание.')
                ->where('about.location.en', 'Berlin')
                ->where('about.location.ru', null)
                ->where('about.show_location', true)
                ->where('about.work_type', 'remote'));
    });

    it('updates the about row and its translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/about', [
            'name' => ['en' => 'Alex', 'ru' => 'Алекс'],
            'title' => ['en' => 'Dev', 'ru' => 'Разработчик'],
            'short_pitch' => ['en' => 'Pitch.', 'ru' => 'Описание.'],
            'location' => ['en' => 'Berlin', 'ru' => 'Берлин'],
            'photo' => 'about/photo.jpg',
            'photo_alt' => ['en' => 'Portrait', 'ru' => null],
            'show_location' => true,
            'timezone' => 'Europe/Berlin',
            'relocation_readiness' => true,
            'availability_status' => 'limited',
            'work_type' => 'hybrid',
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = AboutInfoModel::query()->find(1);
        expect($row)->not->toBeNull()
            ->and($row->photo)->toBe('about/photo.jpg')
            ->and($row->photo_alt)->toBe(['en' => 'Portrait', 'ru' => null])
            ->and($row->show_location)->toBeTrue()
            ->and($row->timezone)->toBe('Europe/Berlin')
            ->and($row->relocation_readiness)->toBeTrue()
            ->and($row->availability_status)->toBe('limited')
            ->and($row->work_type)->toBe('hybrid');

        expect(AboutInfoTranslationModel::query()->count())->toBe(2)
            ->and(AboutInfoTranslationModel::query()->where('locale', 'en')->first()->name)->toBe('Alex')
            ->and(AboutInfoTranslationModel::query()->where('locale', 'ru')->first()->location)->toBe('Берлин');
    });

    it('rejects a missing per-locale name and invalid enums', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/about', [
            'name' => ['en' => '', 'ru' => 'Алекс'],
            'title' => ['en' => 'Dev', 'ru' => 'Разработчик'],
            'short_pitch' => ['en' => 'Pitch.', 'ru' => 'Описание.'],
            'timezone' => 'Europe/Berlin',
            'availability_status' => 'someday',
            'work_type' => 'onsite',
        ])->assertRedirect()
            ->assertSessionHasErrors(['name.en', 'availability_status', 'work_type']);
    });

    it('rejects a non-timezone value', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/about', [
            'name' => ['en' => 'Alex', 'ru' => 'Алекс'],
            'title' => ['en' => 'Dev', 'ru' => 'Разработчик'],
            'short_pitch' => ['en' => 'Pitch.', 'ru' => 'Описание.'],
            'timezone' => 'Not/AZone',
            'availability_status' => 'available',
            'work_type' => 'remote',
        ])->assertRedirect()
            ->assertSessionHasErrors('timezone');
    });
});

describe('ContactController', function (): void {
    it('requires authentication for the list', function (): void {
        $this->get('/admin/contacts')->assertRedirect('/admin/login');
    });

    it('lists contacts with the cursor meta and per-locale names', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $a = ContactModel::factory()->create(['value' => 'octocat', 'sort_order' => 0]);
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $a->id, 'name' => 'GitHub']);
        ContactTranslationModel::factory()->forLocale('ru')->create(['contact_id' => $a->id, 'name' => 'Гитхаб']);
        $b = ContactModel::factory()->create(['value' => '@alex', 'sort_order' => 1]);
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $b->id, 'name' => 'Telegram']);

        $this->get('/admin/contacts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/ContactsListPage')
                ->has('contacts', 2)
                ->where('contacts.0.name', 'Telegram')
                ->where('contacts.1.name', 'GitHub')
                ->where('contacts.0.include_in_resume', true)
                ->where('meta.total', 2)
                ->where('meta.per_page', 12)
                ->where('meta.current_page', 1)
                ->where('meta.sort_column', 'id')
                ->where('meta.cursors.first.id', $b->id)
                ->where('meta.cursors.last.id', $a->id));
    });

    it('stores a contact with per-locale names', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/contacts', [
            'name' => ['en' => 'GitHub', 'ru' => 'Гитхаб'],
            'value' => 'octocat',
            'icon' => 'simple-icons:github',
            'url' => 'https://github.com/alex',
            'include_in_resume' => true,
            'sort_order' => 3,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = ContactModel::query()->first();
        expect($row)->not->toBeNull()
            ->and($row->value)->toBe('octocat')
            ->and($row->icon)->toBe('simple-icons:github')
            ->and($row->url)->toBe('https://github.com/alex')
            ->and($row->include_in_resume)->toBeTrue()
            ->and($row->sort_order)->toBe(3)
            ->and(ContactTranslationModel::query()->where('locale', 'en')->first()->name)->toBe('GitHub')
            ->and(ContactTranslationModel::query()->where('locale', 'ru')->first()->name)->toBe('Гитхаб');
    });

    it('updates a contact and reconciles its translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $contact = ContactModel::factory()->create(['value' => 'old']);
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $contact->id, 'name' => 'Old']);

        $this->put("/admin/contacts/{$contact->id}", [
            'name' => ['en' => 'New', 'ru' => 'Новый'],
            'value' => 'new-value',
            'icon' => null,
            'include_in_resume' => false,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = $contact->fresh();
        expect($row->value)->toBe('new-value')
            ->and($row->icon)->toBeNull()
            ->and($row->include_in_resume)->toBeFalse()
            ->and(ContactTranslationModel::query()->where('contact_id', $contact->id)->pluck('name')->all())
            ->toBe(['New', 'Новый']);
    });

    it('deletes a contact with its translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $contact = ContactModel::factory()->create();
        ContactTranslationModel::factory()->forLocale('en')->create(['contact_id' => $contact->id, 'name' => 'X']);

        $this->delete("/admin/contacts/{$contact->id}")->assertRedirect();

        expect(ContactModel::query()->count())->toBe(0)
            ->and(ContactTranslationModel::query()->count())->toBe(0);
    });

    it('reorders contacts by the zero-indexed payload positions (A4)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $a = ContactModel::factory()->create(['sort_order' => 0]);
        $b = ContactModel::factory()->create(['sort_order' => 1]);
        $c = ContactModel::factory()->create(['sort_order' => 2]);

        $this->post('/admin/contacts/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(ContactModel::query()->find($c->id)->sort_order)->toBe(0)
            ->and(ContactModel::query()->find($a->id)->sort_order)->toBe(1)
            ->and(ContactModel::query()->find($b->id)->sort_order)->toBe(2);
    });

    it('rejects a malformed reorder payload', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/contacts/reorder', ['ids' => ['x']])->assertRedirect()
            ->assertSessionHasErrors('ids.0');

        $this->post('/admin/contacts/reorder', [])->assertRedirect()
            ->assertSessionHasErrors('ids');
    });

    it('validates the contact fields', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/contacts', [
            'name' => ['en' => 'GitHub'],
            'value' => '',
            'url' => 'not-a-url',
        ])->assertRedirect()
            ->assertSessionHasErrors(['value', 'url', 'name.ru']);
    });
});

describe('FeedbackController (admin)', function (): void {
    it('requires authentication for the list', function (): void {
        $this->get('/admin/feedback')->assertRedirect('/admin/login');
    });

    it('lists feedback newest first with the cursor meta', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        FeedbackModel::factory()->create(['sender_name' => 'Old', 'created_at' => now()->subDay()]);
        FeedbackModel::factory()->create(['sender_name' => 'New', 'is_read' => true]);

        $this->get('/admin/feedback')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/FeedbackListPage')
                ->has('feedback', 2)
                ->where('feedback.0.sender_name', 'New')
                ->where('feedback.0.is_read', true)
                ->where('feedback.1.sender_name', 'Old')
                ->where('feedback.1.is_read', false)
                ->where('meta.total', 2)
                ->where('meta.sort_column', 'id')
                ->where('meta.sort_direction', 'desc'));
    });

    it('shows a single feedback item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $item = FeedbackModel::factory()->create(['message' => 'Hello there!']);

        $this->get("/admin/feedback/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portfolio/Admin/Pages/FeedbackDetailPage')
                ->where('item.id', $item->id)
                ->where('item.message', 'Hello there!')
                ->where('item.is_read', false)
                ->where('item.is_forwarded', false));
    });

    it('returns 404 for a missing feedback item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->get('/admin/feedback/99999')->assertNotFound();
    });

    it('marks a feedback item as read and forwarded', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $item = FeedbackModel::factory()->create();

        $this->patchJson("/admin/feedback/{$item->id}/read")->assertOk()
            ->assertJson(['data' => ['id' => $item->id, 'is_read' => true]]);

        $this->patchJson("/admin/feedback/{$item->id}/forwarded")->assertOk()
            ->assertJson(['data' => ['id' => $item->id, 'is_forwarded' => true]]);

        expect($item->fresh()->is_read)->toBeTrue()
            ->and($item->fresh()->is_forwarded)->toBeTrue();
    });

    it('deletes a feedback item', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $item = FeedbackModel::factory()->create();

        $this->delete("/admin/feedback/{$item->id}")->assertRedirect();

        expect(FeedbackModel::query()->count())->toBe(0);
    });
});
