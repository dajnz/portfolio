<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
});

function articleItem(array $attributes = [], array $translations = []): ArticleModel
{
    $article = ArticleModel::factory()->create($attributes);

    $defaults = [
        'en' => [
            'title' => 'Payments Gateway',
            'short_description' => 'A payment gateway article.',
            'content' => '## The problem'.PHP_EOL.'The article body in English with enough words.',
        ],
        'ru' => [
            'title' => 'Платёжный шлюз',
            'short_description' => 'Статья о платёжном шлюзе.',
            'content' => '## Проблема'.PHP_EOL.'Тело статьи на русском с достаточным количеством слов.',
        ],
    ];

    foreach (array_merge($defaults, $translations) as $locale => $fields) {
        ArticleTranslationModel::factory()
            ->forLocale($locale)
            ->create(['article_id' => $article->id] + $fields);
    }

    return $article;
}

function articleTagItem(string $slug = 'php', array $names = []): ArticleTagModel
{
    $tag = ArticleTagModel::factory()->create(['slug' => $slug]);

    $defaults = ['en' => 'PHP', 'ru' => 'ПХП'];
    foreach (array_merge($defaults, $names) as $locale => $name) {
        ArticleTagTranslationModel::factory()->forLocale($locale)->create([
            'article_tag_id' => $tag->id,
            'name' => $name,
        ]);
    }

    return $tag;
}

function articleSkillId(string $name = 'PHP', ?string $icon = 'devicon:php'): int
{
    $group = SkillGroupModel::factory()->create();

    return SkillModel::factory()->create([
        'skill_group_id' => $group->id,
        'name' => $name,
        'icon' => $icon,
    ])->id;
}

describe('AdminTagController', function (): void {
    it('requires authentication', function (): void {
        $this->get('/admin/tags')->assertRedirect('/admin/login');
        $this->get('/admin/tags/create')->assertRedirect('/admin/login');
        $this->post('/admin/tags', ['slug' => 'php', 'name' => ['en' => 'PHP']])->assertRedirect('/admin/login');
        $this->get('/admin/tags/1/edit')->assertRedirect('/admin/login');
        $this->put('/admin/tags/1', ['slug' => 'php', 'name' => ['en' => 'PHP']])->assertRedirect('/admin/login');
        $this->delete('/admin/tags/1')->assertRedirect('/admin/login');
    });

    it('lists tags with the article count and pagination meta', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        articleTagItem('laravel');
        $used = articleTagItem('php'); // Created last — the newest row of the default id-desc order.

        $article = articleItem();
        DB::table('article_tag_pivot')->insert(['article_id' => $article->id, 'tag_id' => $used->id]);

        $this->get('/admin/tags')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Admin/Pages/TagListPage')
                ->has('tags', 2)
                ->where('tags.0.id', $used->id)
                ->where('tags.0.articles_use_count', 1)
                ->where('tags.0.name', 'PHP')
                ->where('meta.total', 2));
    });

    it('searches tags by name across all locales', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        articleTagItem('php');
        $ru = articleTagItem('laravel', ['en' => 'Laravel', 'ru' => 'Ларавел']);

        $this->get('/admin/tags?q='.rawurlencode('ларавел'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tags', 1)
                ->where('tags.0.id', $ru->id));
    });

    it('stores a tag with the auto-generated slug', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/tags', [
            'slug' => 'laravel',
            'name' => ['en' => 'Laravel', 'ru' => 'Ларавел'],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tag = ArticleTagModel::query()->first();
        expect($tag)->not->toBeNull()
            ->and($tag->slug)->toBe('laravel')
            ->and(ArticleTagTranslationModel::query()->where('article_tag_id', $tag->id)->pluck('name', 'locale')->all())
                ->toBe(['en' => 'Laravel', 'ru' => 'Ларавел']);
    });

    it('rejects a tag name that matches an existing skill', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        articleSkillId('PostgreSQL');

        $this->post('/admin/tags', [
            'slug' => 'postgresql',
            'name' => ['en' => 'PostgreSQL', 'ru' => 'ПостгреСQЛ'],
        ])->assertSessionHasErrors(['name']);

        expect(ArticleTagModel::query()->count())->toBe(0);
    });

    it('updates a tag and keeps the uniqueness cross-check with the tag itself ignored', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $tag = articleTagItem('php', ['en' => 'PHP', 'ru' => 'ПХП']);

        $this->put("/admin/tags/{$tag->id}", [
            'slug' => $tag->slug,
            'name' => ['en' => 'PHP', 'ru' => 'ПХП'],
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->put("/admin/tags/{$tag->id}", [
            'slug' => $tag->slug,
            'name' => ['en' => 'Renamed', 'ru' => 'ПХП'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($tag->refresh()->translations()->where('locale', 'en')->first()->name)->toBe('Renamed');
    });

    it('deletes a tag and cascades the pivot rows', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $tag = articleTagItem();
        $article = articleItem();
        DB::table('article_tag_pivot')->insert(['article_id' => $article->id, 'tag_id' => $tag->id]);

        $this->delete("/admin/tags/{$tag->id}")
            ->assertRedirect()
            ->assertSessionHas('notification');

        expect(ArticleTagModel::query()->count())->toBe(0)
            ->and(DB::table('article_tag_pivot')->count())->toBe(0);
    });

    it('creates a tag via the API for inline creation', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->postJson('/api/tags', [
            'slug' => 'vue-js',
            'name' => ['en' => 'Vue.js'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.id', fn (int $id): bool => $id > 0)
            ->assertJsonPath('data.slug', 'vue-js')
            ->assertJsonPath('data.name.en', 'Vue.js')
            ->assertJsonPath('data.name.ru', null);
    });

    it('rejects an API tag creation whose name matches a skill', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        articleSkillId('PHP');

        $this->postJson('/api/tags', [
            'slug' => 'php',
            'name' => ['en' => 'PHP'],
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);
    });
});
