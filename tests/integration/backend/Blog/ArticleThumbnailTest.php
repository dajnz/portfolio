<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Blog;

use App\Features\Blog\Infra\Jobs\GenerateArticleThumbnailJob;
use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Shared\Domain\ValueObjects\ThumbnailPath;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ImageFixtures;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
});

function articleThumbnailTestArticle(array $attributes = []): ArticleModel
{
    $article = ArticleModel::factory()->create($attributes);

    foreach ([
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
    ] as $locale => $fields) {
        ArticleTranslationModel::factory()
            ->forLocale($locale)
            ->create(['article_id' => $article->id] + $fields);
    }

    return $article;
}

function articleThumbnailStoredSize(string $path): array
{
    $image = imagecreatefromstring((string) Storage::disk('rustfs')->get($path));

    return [imagesx($image), imagesy($image)];
}

/**
 * @return array<string, mixed>
 */
function articleThumbnailTestPayload(array $overrides = []): array
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
        'lead_image' => 'blog/lead.webp',
        'lead_image_alt' => ['en' => 'Gateway dashboard', 'ru' => 'Панель шлюза'],
        'is_published' => false,
        'tags' => [],
        'skill_tags' => [],
        'related_articles' => [],
    ], $overrides);
}

describe('article thumbnails', function (): void {
    it('generates both variants and records the 1x path', function (): void {
        $path = 'blog/lead-source.webp';
        Storage::disk('rustfs')->put($path, ImageFixtures::png(1200, 800));
        $article = articleThumbnailTestArticle(['lead_image' => $path]);

        $this->app->call([new GenerateArticleThumbnailJob($article->id), 'handle']);

        $standard = ThumbnailPath::forSource($path)->toStandard();
        $retina = ThumbnailPath::forSource($path)->toRetina();

        expect(Storage::disk('rustfs')->exists($standard))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists($retina))->toBeTrue()
            ->and($article->fresh()->thumbnail_path)->toBe($standard)
            ->and(articleThumbnailStoredSize($standard))->toBe([480, 270])
            ->and(articleThumbnailStoredSize($retina))->toBe([960, 540]);
    });

    it('skips an article without a lead image', function (): void {
        $article = articleThumbnailTestArticle();

        $this->app->call([new GenerateArticleThumbnailJob($article->id), 'handle']);

        expect($article->fresh()->thumbnail_path)->toBeNull()
            ->and(Storage::disk('rustfs')->allFiles())->toBe([]);
    });

    it('exposes the generated pair in the public index props', function (): void {
        $path = 'blog/lead-props.webp';
        articleThumbnailTestArticle([
            'is_published' => true,
            'published_at' => now(),
            'lead_image' => $path,
            'thumbnail_path' => ThumbnailPath::forSource($path)->toStandard(),
        ]);

        $this->get('/blog')
            ->assertInertia(fn (Assert $page) => $page
                ->where('articles.0.lead_image_thumbnail.path', ThumbnailPath::forSource($path)->toStandard())
                ->where('articles.0.lead_image_thumbnail.retina_path', ThumbnailPath::forSource($path)->toRetina()));
    });

    it('schedules the job on store when a lead image is submitted', function (): void {
        Queue::fake();
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/blog', articleThumbnailTestPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Queue::assertPushed(GenerateArticleThumbnailJob::class, 1);
    });

    it('does not schedule the job when no lead image is submitted', function (): void {
        Queue::fake();
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/blog', articleThumbnailTestPayload(['lead_image' => null]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Queue::assertNotPushed(GenerateArticleThumbnailJob::class);
    });

    it('clears the stale thumbnail and schedules the job when the lead image is replaced', function (): void {
        $article = articleThumbnailTestArticle([
            'lead_image' => 'blog/old.webp',
            'thumbnail_path' => ThumbnailPath::forSource('blog/old.webp')->toStandard(),
        ]);

        Queue::fake();
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/blog/'.$article->id, articleThumbnailTestPayload([
            'slug' => $article->slug,
            'lead_image' => 'blog/new.webp',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        expect($article->fresh()->lead_image)->toBe('blog/new.webp')
            ->and($article->fresh()->thumbnail_path)->toBeNull();

        Queue::assertPushed(GenerateArticleThumbnailJob::class, 1);
    });
});
