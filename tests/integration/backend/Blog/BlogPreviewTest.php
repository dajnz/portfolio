<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
});

describe('BlogPreviewController', function (): void {
    it('rejects requests without a valid signature', function (): void {
        $article = previewArticleItem();

        $this->get("/admin/preview/blog/{$article->id}")
            ->assertForbidden();
    });

    it('renders the public article page for an unpublished article via a signed URL', function (): void {
        $article = previewArticleItem(['is_published' => false]);

        $url = URL::temporarySignedRoute(
            'blog.preview',
            now()->addHour(),
            ['article' => $article->id],
        );

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Blog/Public/Pages/BlogArticlePage')
                ->where('article.id', $article->id)
                ->where('article.title', 'Payments Gateway')
                // B211: the preview serves the shared pipeline HTML —
                // the raw Markdown is rendered (headings with ids).
                ->where('article.content_html', fn (string $html) => str_contains($html, '<h2 id="the-problem">')
                    && str_contains($html, 'article body'))
                ->where('reactions', null)
                ->where('seo.robots', 'noindex, nofollow'));
    });

    it('returns 404 for unknown articles', function (): void {
        $url = URL::temporarySignedRoute(
            'blog.preview',
            now()->addHour(),
            ['article' => 999],
        );

        $this->get($url)->assertNotFound();
    });
});

/**
 * Creates a fully translated article with the given base attributes.
 */
function previewArticleItem(array $attributes = [], array $translations = []): ArticleModel
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
