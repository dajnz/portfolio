<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Settings\Infra\Models\SettingModel;
use Database\Seeders\SettingsSeeder;
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

describe('public blog search', function (): void {
    it('ranks by ts_rank and carries the ts_headline <mark> snippet', function (): void {
        $top = $this->publicBlogArticle([], [
            'en' => [
                'title' => 'Payments Gateway',
                'short_description' => 'A payment gateway article.',
                'content' => 'Payments, payments, payments — the body of the article.',
            ],
        ]);
        $low = $this->publicBlogArticle([], [
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
        $this->publicBlogArticle();

        $this->get('/blog?q=the')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 0));
    });

    it('returns everything for an empty query', function (): void {
        $this->publicBlogArticle();
        $this->publicBlogArticle();

        $this->get('/blog?q=')
            ->assertInertia(fn (Assert $page) => $page->has('articles', 2));
    });

    it('scopes the FTS search to the tag-filtered subset', function (): void {
        $tag = $this->publicBlogTag('php');
        $tagged = $this->publicBlogArticle([], [
            'en' => ['title' => 'Payments Gateway', 'short_description' => 'x', 'content' => 'payments here'],
        ]);
        $tagged->articleTags()->sync([$tag->id]);

        $this->publicBlogArticle([], [
            'en' => ['title' => 'Payments Guide', 'short_description' => 'x', 'content' => 'payments there'],
        ]);

        $this->get('/blog?q=payments&article_tags='.$tag->id)
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 1)
                ->where('articles.0.id', $tagged->id));
    });

    it('sanitizes the snippet — only <mark> survives', function (): void {
        $this->publicBlogArticle([], [
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
