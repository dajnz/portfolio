<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\App\UseCases\IncrementArticleViewUseCase;
use App\Features\Blog\Infra\Jobs\IncrementArticleViewJob;
use App\Features\Settings\Infra\Models\SettingModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

describe('article view counter', function (): void {
    it('dispatches the increment job on the public page', function (): void {
        Queue::fake();
        $article = $this->publicBlogArticle();

        $this->get('/blog/'.$article->slug)->assertOk();

        Queue::assertPushed(IncrementArticleViewJob::class, fn (IncrementArticleViewJob $job): bool => $job->articleId === $article->id);
    });

    it('skips the increment on Inertia prefetch requests', function (): void {
        Queue::fake();
        $article = $this->publicBlogArticle();

        $this->get('/blog/'.$article->slug, ['Purpose' => 'prefetch'])->assertOk();

        Queue::assertNotPushed(IncrementArticleViewJob::class);
    });

    it('skips bot User-Agents and missing ones', function (): void {
        $article = $this->publicBlogArticle();

        $increment = app(IncrementArticleViewUseCase::class);

        (new IncrementArticleViewJob($article->id, 'Googlebot/2.1'))->handle($increment);
        (new IncrementArticleViewJob($article->id, null))->handle($increment);
        (new IncrementArticleViewJob($article->id, 'Mozilla/5.0 (X11; Linux x86_64) Chrome/126.0'))->handle($increment);

        $this->assertSame(1, (int) DB::table('article_views')->where('article_id', $article->id)->value('views'));
    });

    it('increments atomically on concurrent first views', function (): void {
        $article = $this->publicBlogArticle();

        $increment = app(IncrementArticleViewUseCase::class);

        (new IncrementArticleViewJob($article->id, 'Browser'))->handle($increment);
        (new IncrementArticleViewJob($article->id, 'Browser'))->handle($increment);
        (new IncrementArticleViewJob($article->id, 'Browser'))->handle($increment);

        $this->assertSame(3, (int) DB::table('article_views')->where('article_id', $article->id)->value('views'));
    });
});
