<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Blog\Infra\Models\ArticleViewModel;
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
 * The shared store/update payload — title/short_description/content
 * required per active locale, plus the tag/skill/related arrays.
 *
 * @return array<string, mixed>
 */
function blogPayload(array $overrides = []): array
{
    return array_merge([
        'slug' => 'payments-gateway',
        'title' => ['en' => 'Payments Gateway', 'ru' => 'Платёжный шлюз'],
        'short_description' => [
            'en' => 'How we built a payment gateway.',
            'ru' => 'Как мы создали платёжный шлюз.',
        ],
        'content' => [
            'en' => '## The problem'.PHP_EOL.'Legacy payouts were slow. This is the article body with enough words to compute a reading time.',
            'ru' => '## Проблема'.PHP_EOL.'Устаревшие выплаты были медленными. Это тело статьи с достаточным количеством слов для расчёта времени чтения.',
        ],
        'lead_image' => 'blog/payments.png',
        'lead_image_alt' => ['en' => 'Gateway dashboard', 'ru' => 'Панель шлюза'],
        'is_featured' => false,
        'is_published' => false,
        'tags' => [],
        'skill_tags' => [],
        'related_articles' => [],
    ], $overrides);
}

/**
 * Creates a fully translated article with the given base attributes.
 */
function blogItem(array $attributes = [], array $translations = []): ArticleModel
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

/**
 * Creates a translated article tag and returns the model.
 */
function blogTagItem(string $slug = 'php', array $names = []): ArticleTagModel
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

/**
 * Seeds a skill in a group and returns its id.
 */
function blogSkillId(string $name = 'PHP', ?string $icon = 'devicon:php'): int
{
    $group = SkillGroupModel::factory()->create();

    return SkillModel::factory()->create([
        'skill_group_id' => $group->id,
        'name' => $name,
        'icon' => $icon,
    ])->id;
}

describe('AdminBlogController', function (): void {
    it('requires authentication for the list and mutations', function (): void {
        $this->get('/admin/blog')->assertRedirect('/admin/login');
        $this->get('/admin/blog/create')->assertRedirect('/admin/login');
        $this->get('/admin/blog/1/edit')->assertRedirect('/admin/login');
        $this->post('/admin/blog', blogPayload())->assertRedirect('/admin/login');
        $this->put('/admin/blog/1', blogPayload())->assertRedirect('/admin/login');
        $this->delete('/admin/blog/1')->assertRedirect('/admin/login');
        $this->patch('/admin/blog/1/restore')->assertRedirect('/admin/login');
        $this->delete('/admin/blog/1/force')->assertRedirect('/admin/login');
        $this->patch('/admin/blog/1/publish')->assertRedirect('/admin/login');
        $this->patch('/admin/blog/1/unpublish')->assertRedirect('/admin/login');
        $this->post('/admin/blog/1/related/2')->assertRedirect('/admin/login');
        $this->delete('/admin/blog/1/related/2')->assertRedirect('/admin/login');
        $this->post('/admin/blog/1/skills/1')->assertRedirect('/admin/login');
        $this->delete('/admin/blog/1/skills/1')->assertRedirect('/admin/login');
        $this->post('/admin/blog/bulk-publish', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->post('/admin/blog/bulk-unpublish', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->post('/admin/blog/bulk-delete', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->post('/admin/blog/bulk-restore', ['ids' => [1]])->assertRedirect('/admin/login');
        $this->post('/admin/blog/bulk-force-delete', ['ids' => [1]])->assertRedirect('/admin/login');
    });

    it('lists articles with the current-locale fields, views and pagination meta', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $older = blogItem(['created_at' => now()->subDays(10)]);
        $newer = blogItem([
            'created_at' => now(),
            'is_published' => true,
            'published_at' => now(),
        ]);
        ArticleViewModel::factory()->create(['article_id' => $newer->id, 'views' => 42]);

        $this->get('/admin/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Admin/Pages/BlogListPage')
                ->has('articles', 2)
                ->where('articles.0.id', $newer->id)
                ->where('articles.0.title', 'Payments Gateway')
                ->where('articles.0.views', 42)
                ->where('articles.0.is_published', true)
                ->where('articles.0.tags', [])
                ->where('articles.0.related_count', 0)
                ->where('meta.total', 2)
                ->has('tags')
                ->where('years', [(int) now()->format('Y')]));
    });

    it('lists the unified tag chips and the related count per article', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $related = blogItem(['slug' => 'second-post']);
        $article = blogItem(); // Created last — the newest row of the default id-desc order.
        $tag = blogTagItem();
        $skillId = blogSkillId();

        DB::table('article_tag_pivot')->insert(['article_id' => $article->id, 'tag_id' => $tag->id]);
        DB::table('article_skill_pivot')->insert(['article_id' => $article->id, 'skill_id' => $skillId]);
        DB::table('related_articles')->insert([
            ['article_id' => $article->id, 'related_article_id' => $related->id],
            ['article_id' => $related->id, 'related_article_id' => $article->id],
        ]);

        $this->get('/admin/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Admin/Pages/BlogListPage')
                ->where('articles.0.tags', [
                    ['id' => $tag->id, 'name' => 'PHP', 'type' => 'tag'],
                    ['id' => $skillId, 'name' => 'PHP', 'type' => 'skill'],
                ])
                ->where('articles.0.related_count', 1));
    });

    it('filters the list by article tags, skill tags, year, status and trash', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $tag = blogTagItem();
        $skillId = blogSkillId('PostgreSQL');
        $matching = blogItem([
            'slug' => 'matching',
            'is_published' => true,
            'published_at' => now()->subYear(),
        ], [
            'en' => [
                'title' => 'PostgreSQL Indexing',
                'short_description' => 'Indexing strategies.',
                'content' => 'A deep dive into postgresql indexing with enough words for the reading time.',
            ],
        ]);
        $other = blogItem(['slug' => 'other']);
        $trashed = blogItem(['slug' => 'trashed', 'deleted_at' => now()]);

        DB::table('article_tag_pivot')->insert(['article_id' => $matching->id, 'tag_id' => $tag->id]);
        DB::table('article_skill_pivot')->insert(['article_id' => $matching->id, 'skill_id' => $skillId]);

        $this->get('/admin/blog?article_tags='.$tag->id.'&skill_tags='.$skillId)
            ->assertInertia(fn (Assert $page) => $page->has('articles', 1)->where('articles.0.id', $matching->id));

        $this->get('/admin/blog?year='.now()->subYear()->year)
            ->assertInertia(fn (Assert $page) => $page->has('articles', 1)->where('articles.0.id', $matching->id));

        $this->get('/admin/blog?published=0')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 1)->where('articles.0.id', $other->id));

        $this->get('/admin/blog?trashed=1')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 1)->where('articles.0.id', $trashed->id));

        $this->get('/admin/blog?q=postgresql')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 1)->where('articles.0.id', $matching->id));
    });

    it('serves the JSON autocomplete variant incl. unpublished and all locales', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem([
            'is_published' => false,
        ], [
            'ru' => [
                'title' => 'Уникальный русский заголовок',
                'short_description' => 'Описание.',
                'content' => 'Содержимое статьи с достаточным количеством слов.',
            ],
        ]);

        // The RU search matches via the RU translation row; the
        // displayed title stays the current-locale (en) projection.
        $this->getJson('/admin/blog?q='.rawurlencode('русский заголовок').'&per_page=8')
            ->assertOk()
            ->assertJsonPath('data.0.id', $article->id)
            ->assertJsonPath('data.0.title', 'Payments Gateway')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'slug', 'published_at', 'is_published', 'created_at']],
                'meta' => ['current_page', 'from', 'to', 'per_page', 'last_page', 'total'],
            ]);

        // With the RU locale cookie the row resolves the RU title
        // (ADR-0026 — unencrypted cookie; getJson needs withCredentials to
        // carry cookies at all).
        $this->withCredentials()
            ->withUnencryptedCookie('locale', 'ru')
            ->getJson('/admin/blog?q='.rawurlencode('русский заголовок').'&per_page=8')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Уникальный русский заголовок');
    });

    it('stores an article with translations, computed reading time, search vector and pivots', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $tag = blogTagItem();
        $skillId = blogSkillId();
        $related = blogItem(['slug' => 'related-post', 'is_published' => true, 'published_at' => now()]);

        $this->post('/admin/blog', blogPayload([
            'is_featured' => true,
            'is_published' => true,
            'tags' => [$tag->id],
            'skill_tags' => [$skillId],
            'related_articles' => [$related->id],
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $article = ArticleModel::where('slug', 'payments-gateway')->first();
        expect($article)->not->toBeNull()
            ->and($article->slug)->toBe('payments-gateway')
            ->and($article->is_published)->toBeTrue()
            ->and($article->published_at)->not->toBeNull()
            ->and($article->is_featured)->toBeTrue()
            ->and($article->lead_image)->toBe('blog/payments.png')
            ->and($article->lead_image_alt)->toBe(['en' => 'Gateway dashboard', 'ru' => 'Панель шлюза']);

        $translations = ArticleTranslationModel::query()->where('article_id', $article->id)->get()->keyBy('locale');
        expect($translations->has('en'))->toBeTrue()
            ->and($translations->has('ru'))->toBeTrue()
            ->and($translations['en']->reading_time_minutes)->toBeGreaterThanOrEqual(1)
            ->and($translations['en']->search_vector)->not->toBeNull();

        expect(DB::table('article_tag_pivot')->where('article_id', $article->id)->pluck('tag_id')->all())->toBe([$tag->id])
            ->and(DB::table('article_skill_pivot')->where('article_id', $article->id)->pluck('skill_id')->all())->toBe([$skillId])
            ->and(DB::table('related_articles')->where('article_id', $article->id)->pluck('related_article_id')->all())->toBe([$related->id]);
    });

    it('validates the per-locale required fields and the slug rules', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/blog', blogPayload([
            'slug' => 'Bad Slug!',
            'title' => ['en' => ''],
            'short_description' => ['ru' => ''],
            'content' => ['ru' => 'too short'],
        ]))->assertSessionHasErrors(['slug', 'title.en', 'short_description.ru', 'content.ru']);
    });

    it('rejects a manual slug override that collides with another article', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        blogItem(['slug' => 'taken-slug']);

        $this->post('/admin/blog', blogPayload(['slug' => 'taken-slug']))
            ->assertSessionHasErrors(['slug']);
    });

    it('rejects related articles that are unpublished or the article itself', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $draft = blogItem(['slug' => 'draft-post']);
        $published = blogItem(['slug' => 'published-post', 'is_published' => true, 'published_at' => now()]);

        $this->post('/admin/blog', blogPayload([
            'related_articles' => [$draft->id, $published->id],
        ]))->assertSessionHasErrors(['related_articles']);

        $this->post('/admin/blog', blogPayload([
            'slug' => 'self-link',
            'related_articles' => [$published->id],
        ]))->assertSessionHasNoErrors();

        $created = ArticleModel::where('slug', 'self-link')->first();

        $this->put("/admin/blog/{$created->id}", blogPayload([
            'slug' => 'self-link',
            'related_articles' => [$created->id],
        ]))->assertSessionHasErrors(['related_articles.0']);
    });

    it('preserves an existing link to an unpublished article on update but rejects NEW unpublished targets (ADR-0024)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem(['slug' => 'linked-article', 'is_published' => true, 'published_at' => now()]);
        $wasPublished = blogItem(['slug' => 'was-published', 'is_published' => true, 'published_at' => now()]);
        $draft = blogItem(['slug' => 'draft-post']);

        // The pair was created while the target was published; the
        // target got unpublished afterwards.
        DB::table('related_articles')->insert([
            ['article_id' => $article->id, 'related_article_id' => $wasPublished->id],
            ['article_id' => $wasPublished->id, 'related_article_id' => $article->id],
        ]);
        DB::table('blog_articles')->where('id', $wasPublished->id)->update(['is_published' => false]);

        // Re-saving with the preserved (now-unpublished) link succeeds.
        $this->put("/admin/blog/{$article->id}", blogPayload([
            'slug' => 'linked-article',
            'title' => ['en' => 'Linked Article', 'ru' => 'Связанная статья'],
            'is_published' => true,
            'related_articles' => [$wasPublished->id],
        ]))->assertSessionHasNoErrors();

        expect(DB::table('related_articles')->where('article_id', $article->id)->where('related_article_id', $wasPublished->id)->exists())->toBeTrue();

        // A NEW unpublished target is still rejected on update.
        $this->put("/admin/blog/{$article->id}", blogPayload([
            'slug' => 'linked-article',
            'title' => ['en' => 'Linked Article', 'ru' => 'Связанная статья'],
            'is_published' => true,
            'related_articles' => [$wasPublished->id, $draft->id],
        ]))->assertSessionHasErrors(['related_articles']);
    });

    it('updates an article, records the old slug and keeps published_at on the first re-save', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem(['slug' => 'old-slug', 'is_published' => true, 'published_at' => now()->subDay()]);

        $this->put("/admin/blog/{$article->id}", blogPayload([
            'slug' => 'new-slug',
            'title' => ['en' => 'Renamed Article', 'ru' => 'Переименованная статья'],
            'is_published' => true,
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $article->refresh();
        expect($article->slug)->toBe('new-slug')
            ->and($article->published_at->toDateString())->toBe(now()->subDay()->toDateString());

        expect(DB::table('old_slugs')->where('old_slug', 'old-slug')->where('entity_type', 'article')->exists())->toBeTrue();
    });

    it('recomputes the reading time and search vector on translation saves', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem();

        $longContent = implode(' ', array_fill(0, 450, 'word'));
        $this->put("/admin/blog/{$article->id}", blogPayload([
            'slug' => $article->slug,
            'content' => [
                'en' => $longContent,
                'ru' => 'Достаточно длинное содержимое статьи на русском языке для прохождения минимального ограничения по длине.',
            ],
        ]))->assertSessionHasNoErrors();

        $translation = ArticleTranslationModel::query()->where('article_id', $article->id)->where('locale', 'en')->first();
        expect($translation->reading_time_minutes)->toBe(3)
            ->and($translation->search_vector)->not->toBeNull();
    });

    it('publishes via the quick action setting published_at once', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem();

        $this->patch("/admin/blog/{$article->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $firstPublishedAt = $article->refresh()->published_at;
        expect($firstPublishedAt)->not->toBeNull();

        $this->patch("/admin/blog/{$article->id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.is_published', false);

        $this->patch("/admin/blog/{$article->id}/publish")
            ->assertOk();

        expect($article->refresh()->published_at->timestamp)->toBe($firstPublishedAt->timestamp);
    });

    it('attaches related articles bidirectionally and rejects duplicates with 409', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem(['slug' => 'first']);
        $related = blogItem(['slug' => 'second', 'is_published' => true, 'published_at' => now()]);

        $this->post("/admin/blog/{$article->id}/related/{$related->id}")
            ->assertOk()
            ->assertJsonPath('data.related_count', 1);

        expect(DB::table('related_articles')->where('article_id', $article->id)->where('related_article_id', $related->id)->exists())->toBeTrue()
            ->and(DB::table('related_articles')->where('article_id', $related->id)->where('related_article_id', $article->id)->exists())->toBeTrue();

        $this->post("/admin/blog/{$article->id}/related/{$related->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', "Article [{$related->id}] is already linked to article [{$article->id}].");

        $this->delete("/admin/blog/{$article->id}/related/{$related->id}")
            ->assertOk()
            ->assertJsonPath('data.related_count', 0);

        expect(DB::table('related_articles')->count())->toBe(0);
    });

    it('validates the related attach target and rejects self-links', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem(['slug' => 'first']);
        $draft = blogItem(['slug' => 'draft-post']);

        $this->post("/admin/blog/{$article->id}/related/{$draft->id}")
            ->assertSessionHasErrors(['relatedArticle']);

        $this->post("/admin/blog/{$article->id}/related/{$article->id}")
            ->assertSessionHasErrors(['relatedArticle']);
    });

    it('attaches and detaches skill tags, rejecting duplicates with 409', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem();
        $skillId = blogSkillId();

        $this->post("/admin/blog/{$article->id}/skills/{$skillId}")
            ->assertOk()
            ->assertJsonPath('data.tags.0', ['id' => $skillId, 'name' => 'PHP', 'type' => 'skill']);

        expect(DB::table('article_skill_pivot')->where('article_id', $article->id)->where('skill_id', $skillId)->exists())->toBeTrue();

        $this->post("/admin/blog/{$article->id}/skills/{$skillId}")
            ->assertStatus(409)
            ->assertJsonPath('message', "Skill [{$skillId}] is already attached to article [{$article->id}].");

        $this->delete("/admin/blog/{$article->id}/skills/{$skillId}")
            ->assertOk()
            ->assertJsonPath('data.tags', []);

        expect(DB::table('article_skill_pivot')->count())->toBe(0);
    });

    it('soft-deletes, restores and force-deletes articles (single + bulk)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $first = blogItem(['slug' => 'first-post']);
        $second = blogItem(['slug' => 'second-post']);

        $this->delete("/admin/blog/{$first->id}")
            ->assertRedirect()
            ->assertSessionHas('notification');

        expect($first->refresh()->trashed())->toBeTrue();

        $this->patch("/admin/blog/{$first->id}/restore")
            ->assertRedirect();

        expect($first->refresh()->trashed())->toBeFalse();

        $this->post('/admin/blog/bulk-delete', ['ids' => [$first->id, $second->id]])->assertOk();
        expect(ArticleModel::query()->count())->toBe(0)
            ->and(ArticleModel::withTrashed()->count())->toBe(2);

        $this->post('/admin/blog/bulk-restore', ['ids' => [$first->id, $second->id]])->assertOk();
        expect(ArticleModel::query()->count())->toBe(2);

        $this->post('/admin/blog/bulk-force-delete', ['ids' => [$first->id, $second->id]])->assertOk();
        expect(ArticleModel::withTrashed()->count())->toBe(0);
    });

    it('performs bulk publish and unpublish', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $first = blogItem(['slug' => 'first-post']);
        $second = blogItem(['slug' => 'second-post']);

        $this->post('/admin/blog/bulk-publish', ['ids' => [$first->id, $second->id]])->assertOk();

        expect(ArticleModel::query()->where('is_published', true)->count())->toBe(2)
            ->and($first->refresh()->published_at)->not->toBeNull();

        $this->post('/admin/blog/bulk-unpublish', ['ids' => [$first->id, $second->id]])->assertOk();
        expect(ArticleModel::query()->where('is_published', true)->count())->toBe(0)
            ->and($first->refresh()->published_at)->not->toBeNull();
    });

    it('deletes the lead image file on force delete and preserves the old slug', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        Storage::disk('rustfs')->put('blog/to-delete.png', 'x');
        $article = blogItem(['slug' => 'force-me', 'lead_image' => 'blog/to-delete.png']);

        $this->delete("/admin/blog/{$article->id}/force")
            ->assertRedirect();

        expect(Storage::disk('rustfs')->exists('blog/to-delete.png'))->toBeFalse()
            ->and(DB::table('old_slugs')->where('old_slug', 'force-me')->where('entity_type', 'article')->exists())->toBeTrue();
    });

    it('invalidates the sitemap cache on published saves and deletes', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem(['slug' => 'sitemap-post', 'is_published' => true, 'published_at' => now()]);

        Cache::put('sitemap:xml', '<xml/>');
        $this->put("/admin/blog/{$article->id}", blogPayload(['slug' => 'sitemap-post']))->assertSessionHasNoErrors();
        expect(Cache::has('sitemap:xml'))->toBeFalse();

        // The payload above unpublishes the article (is_published
        // defaults to false) — a fresh published article for the
        // delete-side invalidation.
        $published = blogItem(['slug' => 'sitemap-published', 'is_published' => true, 'published_at' => now()]);

        Cache::put('sitemap:xml', '<xml/>');
        $this->delete("/admin/blog/{$published->id}")->assertRedirect();
        expect(Cache::has('sitemap:xml'))->toBeFalse();
    });

    it('renders the edit form with the stored article data and the picker options', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem(['is_featured' => true]);
        $related = blogItem(['slug' => 'related-post', 'is_published' => true, 'published_at' => now()]);
        $tag = blogTagItem();
        $skillId = blogSkillId('PHP', null);

        DB::table('article_tag_pivot')->insert(['article_id' => $article->id, 'tag_id' => $tag->id]);
        DB::table('article_skill_pivot')->insert(['article_id' => $article->id, 'skill_id' => $skillId]);
        DB::table('related_articles')->insert([
            ['article_id' => $article->id, 'related_article_id' => $related->id],
            ['article_id' => $related->id, 'related_article_id' => $article->id],
        ]);

        $this->get("/admin/blog/{$article->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Admin/Pages/BlogFormPage')
                ->where('article.id', $article->id)
                ->where('article.title', ['en' => 'Payments Gateway', 'ru' => 'Платёжный шлюз'])
                ->where('article.is_featured', true)
                ->where('article.tags', [['id' => $tag->id, 'slug' => 'php', 'name' => 'PHP']])
                ->where('article.skill_tags', [['id' => $skillId, 'name' => 'PHP', 'icon' => null]])
                ->where('article.related_article_ids', [$related->id])
                ->where('article.related.0.id', $related->id)
                ->has('tags')
                ->has('articles')
                ->where('preview_url', fn (mixed $url) => is_string($url) && str_contains($url, 'signature')));
    });

    it('excludes the article itself from the related picker options', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $article = blogItem(['slug' => 'first', 'is_published' => true, 'published_at' => now()]);

        $this->get("/admin/blog/{$article->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('articles', fn (\Illuminate\Support\Collection $articles): bool => count(array_filter(
                    $articles->all(),
                    fn (array $a): bool => $a['id'] === $article->id,
                )) === 0));
    });
});
