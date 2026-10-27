<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Portfolio\Infra\Models\AboutInfoModel;
use App\Features\Settings\Infra\Models\SettingModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\SeedsBlogData;

uses(SeedsBlogData::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');

    // 2 items per page makes the cursor pagination assertions cheap.
    SettingModel::query()->updateOrCreate(
        ['key' => 'articles_per_page'],
        ['value' => '2', 'type' => 'integer'],
    );
});

describe('public blog index', function (): void {
    it('lists published articles by published_at DESC with the opaque cursor', function (): void {
        $a = $this->publicBlogArticle(['published_at' => now()->subDays(5)]);
        $b = $this->publicBlogArticle(['published_at' => now()->subDays(4)]);
        $c = $this->publicBlogArticle(['published_at' => now()->subDays(3)]);

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
        $this->get('/blog?cursor='.$this->encodedBlogCursor((int) $b->id, $b->published_at->format('Y-m-d H:i:s')))
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 3)
                ->where('articles.2.id', $a->id)
                // The final batch (1 item < perPage) is incomplete — no
                // more cursors.
                ->where('has_more', false)
                ->where('next_cursor', null));
    });

    it('excludes unpublished and trashed articles', function (): void {
        $this->publicBlogArticle();
        $draft = $this->publicBlogArticle(['is_published' => false, 'published_at' => null]);
        $trashed = $this->publicBlogArticle(['deleted_at' => now()]);

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 1)
                ->whereNot('articles.0.id', $draft->id)
                ->whereNot('articles.0.id', $trashed->id));
    });

    it('serves the locale-resolved variant via the locale-prefixed URL', function (): void {
        $this->publicBlogArticle();

        $this->get('/ru/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('articles.0.title', 'Платёжный шлюз')
                ->where('articles.0.short_description', 'Статья о платёжном шлюзе.'));
    });

    it('filters by the article-tag AND/intersect across both sources', function (): void {
        $php = $this->publicBlogTag('php');
        $laravel = $this->publicBlogTag('laravel');
        $skill = $this->publicBlogSkillId('Vue');

        $both = $this->publicBlogArticle();
        $both->articleTags()->sync([$php->id, $laravel->id]);
        $both->skillTags()->sync([$skill]);

        $phpOnly = $this->publicBlogArticle();
        $phpOnly->articleTags()->sync([$php->id]);

        $skillOnly = $this->publicBlogArticle();
        $skillOnly->skillTags()->sync([$skill]);

        $this->publicBlogArticle();

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
        $this->publicBlogArticle(['published_at' => now()->setDate(2026, 1, 15)]);
        $this->publicBlogArticle(['published_at' => now()->setDate(2025, 6, 1)]);

        $this->get('/blog?year=2025')
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 1)
                ->where('articles.0.published_at', fn (mixed $date) => str_starts_with((string) $date, '2025-')));

        $this->get('/blog?year=9999')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 0));
    });

    it('exposes the tag options and the distinct publication years', function (): void {
        $php = $this->publicBlogTag('php');
        $vue = $this->publicBlogTag('vue');
        $this->publicBlogArticle(['published_at' => now()->setDate(2026, 3, 1)]);
        $this->publicBlogArticle(['published_at' => now()->setDate(2025, 3, 1)]);
        $this->publicBlogArticle(['is_published' => false, 'published_at' => null]);

        $this->get('/blog')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tags.0.id', $php->id)
                ->where('tags.1.id', $vue->id)
                ->where('years', [2026, 2025]));
    });

    it('buckets the publication year in the owner timezone', function (): void {
        AboutInfoModel::factory()->create(['id' => 1, 'timezone' => 'Europe/Berlin']);
        $this->publicBlogArticle(['published_at' => '2026-12-31 23:30:00']);

        $this->get('/blog?year=2027')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 1));

        $this->get('/blog')
            ->assertInertia(fn (Assert $page) => $page->where('years', [2027]));

        $this->getJson('/blog?year=2027')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('renders the SEO props of the index', function (): void {
        $this->publicBlogArticle();

        $this->get('/blog')
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.title', 'Blog — ')
                ->where('seo.canonical_url', url('/blog'))
                ->where('seo.og_url', url('/blog'))
                ->where('seo.og_type', 'website'));
    });

    it('serves the JSON dual-response variant with raw values', function (): void {
        $a = $this->publicBlogArticle(['published_at' => now()->subDays(2)]);
        $b = $this->publicBlogArticle(['published_at' => now()->subDays(1)]);

        $this->getJson('/blog?per_page=1&page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.0.slug', $a->slug);
    });

    it('serves the index props from the public page cache on a plain request', function (): void {
        config()->set('portfolio.public_cache.enabled', true);

        $this->publicBlogArticle([], [
            'en' => ['title' => 'Cached title', 'short_description' => 'First.', 'content' => 'first body'],
        ]);

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('articles.0.title', 'Cached title'));

        // A raw write behind the cache must not be observed while the entry is warm.
        ArticleTranslationModel::query()->update(['title' => 'Changed behind the cache']);

        $this->get('/blog')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('articles.0.title', 'Cached title'));

        // Filtered requests are never served from the cache.
        $this->get('/blog?q=cached')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('articles.0.title', 'Changed behind the cache'));
    });

    it('prefix-matches the last word of the JSON autocomplete query', function (): void {
        $this->publicBlogArticle([], [
            'en' => ['title' => 'Vuejs Patterns', 'short_description' => 'Vue patterns.', 'content' => 'vuejs composition api'],
        ]);

        // "vues" is not a word — the prefix form makes it match "vuejs".
        $this->getJson('/blog?q=vues')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Vuejs Patterns');
    });
});
