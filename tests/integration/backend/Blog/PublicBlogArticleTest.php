<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\Infra\Models\ArticleViewModel;
use App\Features\Settings\Infra\Models\SettingModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\SeedsBlogData;

uses(SeedsBlogData::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');

    // 2 items per page makes the pagination assertions cheap.
    SettingModel::query()->updateOrCreate(
        ['key' => 'articles_per_page'],
        ['value' => '2', 'type' => 'integer'],
    );
});

describe('public blog article', function (): void {
    it('renders the article with the pipeline HTML, views and SEO', function (): void {
        $tag = $this->publicBlogTag('php');
        $article = $this->publicBlogArticle([
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
                ->where('seo.og_url', url('/blog/'.$article->slug))
                ->where('seo.og_image', url('/assets/blog/cover.png'))
                ->where('seo.article.tags', ['PHP'])
                ->where('seo.article.published_time', fn (mixed $value) => is_string($value) && $value !== '')
                ->where('seo.json_ld.0.@type', 'BlogPosting')
                ->where('seo.json_ld.0.keywords', ['PHP'])
                ->where('seo.json_ld.0.image', url('/assets/blog/cover.png'))
                ->where('seo.json_ld.1.@type', 'BreadcrumbList'));
    });

    it('sets the BlogPosting publisher logo from the about photo (seo.md:141)', function (): void {
        \App\Features\Portfolio\Infra\Models\AboutInfoModel::factory()->create([
            'id' => 1,
            'photo' => 'about/photo.jpg',
        ]);

        $article = $this->publicBlogArticle([], [
            'en' => [
                'title' => 'With Logo',
                'short_description' => 'x',
                'content' => 'Body text.',
            ],
        ]);

        $this->get('/blog/'.$article->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('seo.json_ld.0.publisher.logo.@type', 'ImageObject')
                ->where('seo.json_ld.0.publisher.logo.url', url('/assets/about/photo.jpg')));
    });

    it('strips raw HTML from the rendered content', function (): void {
        $article = $this->publicBlogArticle([], [
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
        $article = $this->publicBlogArticle();
        $related = $this->publicBlogArticle();
        $hidden = $this->publicBlogArticle(['is_published' => false, 'published_at' => null]);

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
        $draft = $this->publicBlogArticle(['is_published' => false, 'published_at' => null]);

        $this->get('/blog/'.$draft->slug)->assertNotFound();
        $this->get('/blog/never-existed')->assertNotFound();
    });

    it('301-redirects old slugs, preserving the locale prefix', function (): void {
        $article = $this->publicBlogArticle();
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
        $article = $this->publicBlogArticle(['deleted_at' => now()]);
        DB::table('old_slugs')->insert([
            'old_slug' => 'gone-post',
            'entity_type' => 'article',
            'entity_id' => $article->id,
            'created_at' => now(),
        ]);

        $this->get('/blog/gone-post')->assertNotFound();
    });
});
