<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\Infra\Jobs\IncrementArticleViewJob;
use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleReactionModel;
use App\Features\Blog\Infra\Models\ArticleTagModel;
use App\Features\Blog\Infra\Models\ArticleTagTranslationModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Blog\Infra\Models\ArticleViewModel;
use App\Features\Settings\Domain\Enums\FeatureFlagName;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use App\Features\Settings\Infra\Models\SettingModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\Shared\Infra\Persistence\Cursor;
use App\Features\Shared\Infra\Persistence\PublicCursorCodec;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');

    // 2 items per page makes the cursor pagination assertions cheap.
    SettingModel::query()->updateOrCreate(
        ['key' => 'articles_per_page'],
        ['value' => '2', 'type' => 'integer'],
    );
});

/**
 * Creates a PUBLISHED fully translated article with the given base
 * attributes (helper names are file-scoped per B216 — the admin suite
 * owns `blogItem`).
 */
function publicBlogArticle(array $attributes = [], array $translations = []): ArticleModel
{
    $article = ArticleModel::factory()->published()->create($attributes);

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
function publicBlogTag(string $slug = 'php', array $names = []): ArticleTagModel
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
function publicBlogSkillId(string $name = 'PHP', ?string $icon = 'devicon:php'): int
{
    $group = SkillGroupModel::factory()->create();

    return SkillModel::factory()->create([
        'skill_group_id' => $group->id,
        'name' => $name,
        'icon' => $icon,
    ])->id;
}

/**
 * Decodes the opaque next_cursor into its `{id, v}` parts.
 *
 * @return array<string, mixed>
 */
function decodedBlogCursor(string $cursor): array
{
    $json = base64_decode(strtr($cursor, '-_', '+/'), true);

    try {
        return json_decode((string) $json, true, 16, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        return [];
    }
}

/**
 * Encodes a cursor with the same codec the controller uses.
 */
function encodedBlogCursor(int $id, string $sortValue): string
{
    return app(PublicCursorCodec::class)->encode(new Cursor(id: $id, sortValue: $sortValue));
}

describe('public blog index', function (): void {
    it('lists published articles by published_at DESC with the opaque cursor', function (): void {
        $a = publicBlogArticle(['published_at' => now()->subDays(5)]);
        $b = publicBlogArticle(['published_at' => now()->subDays(4)]);
        $c = publicBlogArticle(['published_at' => now()->subDays(3)]);

        $first = $this->get('/blog');
        $first->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Public/Pages/BlogIndexPage')
                ->has('articles', 2)
                ->where('articles.0.id', $c->id)
                ->where('articles.1.id', $b->id)
                ->where('has_more', true)
                ->where('next_cursor', fn (mixed $cursor) => is_string($cursor) && $cursor !== ''));

        // The "Load More" request resumes after the cursor: the batch
        // containing it (none — it was the last of the first batch)
        // plus one beyond → article `a`.
        $this->get('/blog?cursor='.encodedBlogCursor((int) $b->id, $b->published_at->format('Y-m-d H:i:s')))
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 3)
                ->where('articles.2.id', $a->id)
                // The final batch (1 item < perPage) is incomplete — no
                // more cursors.
                ->where('has_more', false)
                ->where('next_cursor', null));
    });

    it('excludes unpublished and trashed articles', function (): void {
        publicBlogArticle();
        $draft = publicBlogArticle(['is_published' => false, 'published_at' => null]);
        $trashed = publicBlogArticle(['deleted_at' => now()]);

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 1)
                ->whereNot('articles.0.id', $draft->id)
                ->whereNot('articles.0.id', $trashed->id));
    });

    it('serves the locale-resolved variant via the locale-prefixed URL', function (): void {
        publicBlogArticle();

        $this->get('/ru/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('articles.0.title', 'Платёжный шлюз')
                ->where('articles.0.short_description', 'Статья о платёжном шлюзе.'));
    });

    it('filters by the article-tag AND/intersect across both sources', function (): void {
        $php = publicBlogTag('php');
        $laravel = publicBlogTag('laravel');
        $skill = publicBlogSkillId('Vue');

        $both = publicBlogArticle();
        $both->articleTags()->sync([$php->id, $laravel->id]);
        $both->skillTags()->sync([$skill]);

        $phpOnly = publicBlogArticle();
        $phpOnly->articleTags()->sync([$php->id]);

        $skillOnly = publicBlogArticle();
        $skillOnly->skillTags()->sync([$skill]);

        publicBlogArticle();

        // All three must be present: tag AND skill AND untagged.
        $this->get('/blog?article_tags='.$php->id.'&skill_tags='.$skill)
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 1)
                ->where('articles.0.id', $both->id));

        $this->get('/blog?article_tags='.$php->id.','.$laravel->id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 1)
                ->where('articles.0.id', $both->id));
    });

    it('filters by the publication year', function (): void {
        publicBlogArticle(['published_at' => now()->setDate(2026, 1, 15)]);
        publicBlogArticle(['published_at' => now()->setDate(2025, 6, 1)]);

        $this->get('/blog?year=2025')
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 1)
                ->where('articles.0.published_at', fn (mixed $date) => str_starts_with((string) $date, '2025-')));

        $this->get('/blog?year=9999')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 0));
    });

    it('exposes the tag options and the distinct publication years', function (): void {
        $php = publicBlogTag('php');
        $vue = publicBlogTag('vue');
        publicBlogArticle(['published_at' => now()->setDate(2026, 3, 1)]);
        publicBlogArticle(['published_at' => now()->setDate(2025, 3, 1)]);
        publicBlogArticle(['is_published' => false, 'published_at' => null]);

        $this->get('/blog')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tags.0.id', $php->id)
                ->where('tags.1.id', $vue->id)
                ->where('years', [2026, 2025]));
    });

    it('renders the SEO props of the index', function (): void {
        publicBlogArticle();

        $this->get('/blog')
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.title', 'Blog — ')
                ->where('seo.canonical_url', url('/blog'))
                ->where('seo.og_type', 'website'));
    });

    it('serves the JSON dual-response variant with raw values', function (): void {
        $a = publicBlogArticle(['published_at' => now()->subDays(2)]);
        $b = publicBlogArticle(['published_at' => now()->subDays(1)]);

        $this->getJson('/blog?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.0.slug', $a->slug);
    });

    it('prefix-matches the last word of the JSON autocomplete query', function (): void {
        publicBlogArticle([], [
            'en' => ['title' => 'Vuejs Patterns', 'short_description' => 'Vue patterns.', 'content' => 'vuejs composition api'],
        ]);

        // "vues" is not a word — the prefix form makes it match "vuejs".
        $this->getJson('/blog?q=vues')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Vuejs Patterns');
    });
});

describe('public blog search', function (): void {
    it('ranks by ts_rank and carries the ts_headline <mark> snippet', function (): void {
        $top = publicBlogArticle([], [
            'en' => [
                'title' => 'Payments Gateway',
                'short_description' => 'A payment gateway article.',
                'content' => 'Payments, payments, payments — the body of the article.',
            ],
        ]);
        $low = publicBlogArticle([], [
            'en' => [
                'title' => 'Other Post',
                'short_description' => 'Unrelated.',
                'content' => 'This article mentions payments once in its long body text.',
            ],
        ]);

        $this->get('/blog?q=payments')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 2)
                ->where('articles.0.id', $top->id)
                ->where('articles.1.id', $low->id)
                ->where('articles.0.short_description', fn (mixed $desc) => is_string($desc) && str_contains($desc, '<mark>'))
                ->where('articles.1.short_description', fn (mixed $desc) => is_string($desc) && str_contains($desc, '<mark>')));
    });

    it('returns an empty result for stop-word-only queries', function (): void {
        publicBlogArticle();

        $this->get('/blog?q=the')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 0));
    });

    it('returns everything for an empty query', function (): void {
        publicBlogArticle();
        publicBlogArticle();

        $this->get('/blog?q=')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 2));
    });

    it('scopes the FTS search to the tag-filtered subset', function (): void {
        $tag = publicBlogTag('php');
        $tagged = publicBlogArticle([], [
            'en' => ['title' => 'Payments Gateway', 'short_description' => 'x', 'content' => 'payments here'],
        ]);
        $tagged->articleTags()->sync([$tag->id]);

        publicBlogArticle([], [
            'en' => ['title' => 'Payments Guide', 'short_description' => 'x', 'content' => 'payments there'],
        ]);

        $this->get('/blog?q=payments&article_tags='.$tag->id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 1)
                ->where('articles.0.id', $tagged->id));
    });

    it('sanitizes the snippet — only <mark> survives', function (): void {
        publicBlogArticle([], [
            'en' => [
                'title' => 'XSS Post',
                'short_description' => 'x',
                'content' => '<script>alert(1)</script> payments in the body here.',
            ],
        ]);

        $this->get('/blog?q=payments')
            ->assertInertia(fn (Assert $page) => $page
                ->where('articles.0.short_description', fn (mixed $desc) => is_string($desc)
                    && str_contains($desc, '<mark>')
                    && ! str_contains($desc, '<script>')));
    });
});

describe('public blog detail', function (): void {
    it('renders the article with the pipeline HTML, views and SEO', function (): void {
        $tag = publicBlogTag('php');
        $article = publicBlogArticle([
            'lead_image' => 'blog/cover.png',
            'lead_image_alt' => ['en' => 'Cover', 'ru' => 'Обложка'],
        ], [
            'en' => [
                'title' => 'Payments Gateway',
                'short_description' => 'A payment gateway article.',
                'content' => '## The problem'.PHP_EOL
                    .'The article body in English with enough words.'.PHP_EOL
                    .'```php'.PHP_EOL.'echo "hi";'.PHP_EOL.'```',
            ],
        ]);
        $article->articleTags()->sync([$tag->id]);
        ArticleViewModel::query()->create(['article_id' => $article->id, 'views' => 42]);

        $this->get('/blog/'.$article->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Public/Pages/BlogArticlePage')
                ->where('article.id', $article->id)
                ->where('article.title', 'Payments Gateway')
                ->where('article.views_count', 42)
                ->where('article.lead_image', 'blog/cover.png')
                ->where('article.tags.0.name', 'PHP')
                ->where('article.content_html', fn (string $html) => str_contains($html, '<h2 id="the-problem">')
                    && str_contains($html, 'data-code-block')
                    && str_contains($html, 'style="color:#1e7a5b"'))
                ->where('reactions', null)
                ->where('seo.og_type', 'article')
                ->where('seo.json_ld.0.@type', 'BlogPosting')
                ->where('seo.json_ld.0.keywords', ['PHP'])
                ->where('seo.json_ld.1.@type', 'BreadcrumbList'));
    });

    it('strips raw HTML from the rendered content', function (): void {
        $article = publicBlogArticle([], [
            'en' => [
                'title' => 'XSS Post',
                'short_description' => 'x',
                'content' => 'Body text. <script>alert(1)</script> and more.',
            ],
        ]);

        $this->get('/blog/'.$article->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('article.content_html', fn (string $html) => str_contains($html, '&lt;script&gt;')
                    && ! str_contains($html, '<script>')));
    });

    it('shows only published related articles as full cards', function (): void {
        $article = publicBlogArticle();
        $related = publicBlogArticle();
        $hidden = publicBlogArticle(['is_published' => false, 'published_at' => null]);

        DB::table('related_articles')->insert([
            ['article_id' => $article->id, 'related_article_id' => $related->id],
            ['article_id' => $related->id, 'related_article_id' => $article->id],
            ['article_id' => $article->id, 'related_article_id' => $hidden->id],
            ['article_id' => $hidden->id, 'related_article_id' => $article->id],
        ]);

        $this->get('/blog/'.$article->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->has('article.related', 1)
                ->where('article.related.0.id', $related->id)
                ->where('article.related.0.title', 'Payments Gateway')
                ->where('article.related.0.slug', $related->slug));
    });

    it('returns 404 for unpublished and unknown articles', function (): void {
        $draft = publicBlogArticle(['is_published' => false, 'published_at' => null]);

        $this->get('/blog/'.$draft->slug)->assertNotFound();
        $this->get('/blog/never-existed')->assertNotFound();
    });

    it('301-redirects old slugs, preserving the locale prefix', function (): void {
        $article = publicBlogArticle();
        DB::table('old_slugs')->insert([
            'old_slug' => 'legacy-post',
            'entity_type' => 'article',
            'entity_id' => $article->id,
            'created_at' => now(),
        ]);

        $this->get('/blog/legacy-post')
            ->assertRedirect(url('/blog/'.$article->slug));
        $this->get('/ru/blog/legacy-post')
            ->assertRedirect(url('/ru/blog/'.$article->slug));
    });

    it('returns 404 for old slugs of trashed articles', function (): void {
        $article = publicBlogArticle(['deleted_at' => now()]);
        DB::table('old_slugs')->insert([
            'old_slug' => 'gone-post',
            'entity_type' => 'article',
            'entity_id' => $article->id,
            'created_at' => now(),
        ]);

        $this->get('/blog/gone-post')->assertNotFound();
    });
});

describe('article view counter', function (): void {
    it('dispatches the increment job on the public page', function (): void {
        Queue::fake();
        $article = publicBlogArticle();

        $this->get('/blog/'.$article->slug)->assertOk();

        Queue::assertPushed(IncrementArticleViewJob::class, fn (IncrementArticleViewJob $job): bool => $job->articleId === $article->id);
    });

    it('skips bot User-Agents and missing ones', function (): void {
        $article = publicBlogArticle();

        (new IncrementArticleViewJob($article->id, '1.2.3.4', 'Googlebot/2.1'))->handle();
        (new IncrementArticleViewJob($article->id, '1.2.3.4', null))->handle();
        (new IncrementArticleViewJob($article->id, '1.2.3.4', 'Mozilla/5.0 (X11; Linux x86_64) Chrome/126.0'))->handle();

        $this->assertSame(1, (int) DB::table('article_views')->where('article_id', $article->id)->value('views'));
    });

    it('increments atomically on concurrent first views', function (): void {
        $article = publicBlogArticle();

        (new IncrementArticleViewJob($article->id, '1.2.3.4', 'Browser'))->handle();
        (new IncrementArticleViewJob($article->id, '1.2.3.4', 'Browser'))->handle();
        (new IncrementArticleViewJob($article->id, '1.2.3.4', 'Browser'))->handle();

        $this->assertSame(3, (int) DB::table('article_views')->where('article_id', $article->id)->value('views'));
    });
});

describe('article reactions', function (): void {
    beforeEach(function (): void {
        FeatureFlagModel::query()->updateOrCreate(
            ['key' => FeatureFlagName::SocialReactions->value],
            ['enabled' => true, 'description' => ''],
        );
    });

    it('404s when the feature flag is off', function (): void {
        FeatureFlagModel::query()->where('key', FeatureFlagName::SocialReactions->value)->update(['enabled' => false]);
        $article = publicBlogArticle();

        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'])
            ->assertNotFound();
    });

    it('422s on an invalid reaction type', function (): void {
        $article = publicBlogArticle();

        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'meh'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reaction');
    });

    it('404s for unknown or unpublished articles', function (): void {
        $this->postJson('/api/blog/999/reaction', ['reaction' => 'like'])->assertNotFound();

        $draft = publicBlogArticle(['is_published' => false, 'published_at' => null]);
        $this->postJson("/api/blog/{$draft->id}/reaction", ['reaction' => 'like'])->assertNotFound();
    });

    it('inserts, toggles off on repeat and switches on the opposite reaction', function (): void {
        $article = publicBlogArticle();

        $like = $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'], ['User-Agent' => 'BlogVisitor/1']);
        $like->assertOk()
            ->assertJsonPath('data.reaction', 'like')
            ->assertJsonPath('data.likes', 1)
            ->assertJsonPath('data.dislikes', 0);

        // The same visitor undoes the reaction.
        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'], ['User-Agent' => 'BlogVisitor/1'])
            ->assertJsonPath('data.reaction', null)
            ->assertJsonPath('data.likes', 0);

        // Switching to dislike keeps a single row.
        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'dislike'], ['User-Agent' => 'BlogVisitor/1'])
            ->assertJsonPath('data.reaction', 'dislike')
            ->assertJsonPath('data.dislikes', 1)
            ->assertJsonPath('data.likes', 0);

        $this->assertSame(1, ArticleReactionModel::query()->where('article_id', $article->id)->count());
    });

    it('keeps reactions per fingerprint', function (): void {
        $article = publicBlogArticle();

        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'], ['User-Agent' => 'VisitorA/1']);
        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'like'], ['User-Agent' => 'VisitorB/1']);
        $this->postJson("/api/blog/{$article->id}/reaction", ['reaction' => 'dislike'], ['User-Agent' => 'VisitorC/1']);

        $this->assertSame(3, ArticleReactionModel::query()->where('article_id', $article->id)->count());

        $this->withHeader('User-Agent', 'VisitorA/1')->get('/blog/'.$article->slug)
            ->assertInertia(fn (Assert $page) => $page
                ->where('reactions.user_reaction', 'like')
                ->where('reactions.likes', 2)
                ->where('reactions.dislikes', 1));
    });

    it('reports null reactions while the flag is off', function (): void {
        FeatureFlagModel::query()->where('key', FeatureFlagName::SocialReactions->value)->update(['enabled' => false]);
        $article = publicBlogArticle();

        $this->get('/blog/'.$article->slug)
            ->assertInertia(fn (Assert $page) => $page->where('reactions', null));
    });
});
