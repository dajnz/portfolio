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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
});

function feedArticle(array $attributes = [], array $translations = []): ArticleModel
{
    $article = ArticleModel::factory()->published()->create($attributes);

    foreach (array_merge([
        'en' => ['title' => 'Payments Gateway', 'short_description' => 'A payment gateway article.', 'content' => 'Body with enough words for the reading time.'],
        'ru' => ['title' => 'Платёжный шлюз', 'short_description' => 'Статья о платёжном шлюзе.', 'content' => 'Тело статьи с достаточным количеством слов.'],
    ], $translations) as $locale => $fields) {
        ArticleTranslationModel::factory()
            ->forLocale($locale)
            ->create(['article_id' => $article->id] + $fields);
    }

    return $article;
}

function feedTag(string $slug = 'php', string $en = 'PHP'): ArticleTagModel
{
    $tag = ArticleTagModel::factory()->create(['slug' => $slug]);
    ArticleTagTranslationModel::factory()->forLocale('en')->create([
        'article_tag_id' => $tag->id,
        'name' => $en,
    ]);

    return $tag;
}

describe('FeedController', function (): void {
    it('serves the 20 most recent published articles as RSS 2.0', function (): void {
        $old = feedArticle(['published_at' => now()->subDays(10)]);
        $new = feedArticle(['published_at' => now()->subDays(1)]);

        for ($i = 0; $i < 22; $i++) {
            feedArticle(['slug' => 'article-'.$i, 'published_at' => now()->subHours($i + 1)]);
        }

        $response = $this->get('/rss');
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = $response->getContent();
        expect($xml)->toBeString()
            ->and(Str::startsWith($xml, '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0">'))->toBeTrue()
            ->and(substr_count($xml, '<item>'))->toBe(20)
            ->and($xml)->toContain('Payments Gateway');

        // The newest item sorts first (article-0 = now - 1 hour).
        expect(strpos($xml, 'article-0') < strpos($xml, 'article-1'))->toBeTrue();
    });

    it('includes per-item pubDate, author and link', function (): void {
        feedArticle(['slug' => 'payments-gateway', 'published_at' => now()->setDateTime(2026, 1, 15, 12, 0, 0)]);

        $xml = (string) $this->get('/rss')->getContent();

        expect($xml)->toContain('<pubDate>Thu, 15 Jan 2026 12:00:00 +0000</pubDate>')
            ->and($xml)->toContain('<author>')
            ->and($xml)->toContain('<link>'.url('/blog/payments-gateway').'</link>');
    });

    it('emits categories for article tags and attached skills', function (): void {
        $php = feedTag('php');
        $laravel = feedTag('laravel', 'Laravel');
        $skillGroup = SkillGroupModel::factory()->create();
        $skill = SkillModel::factory()->create([
            'skill_group_id' => $skillGroup->id,
            'name' => 'Vue',
            'icon' => 'devicon:vuejs',
        ]);

        $article = feedArticle();
        $article->articleTags()->sync([$php->id, $laravel->id]);
        $article->skillTags()->sync([$skill->id]);

        $xml = (string) $this->get('/rss')->getContent();

        expect($xml)->toContain('<category>PHP</category>')
            ->and($xml)->toContain('<category>Laravel</category>')
            ->and($xml)->toContain('<category>Vue</category>');
    });

    it('emits the lead image as an enclosure', function (): void {
        feedArticle(['lead_image' => 'blog/cover.png']);

        $xml = (string) $this->get('/rss')->getContent();

        expect($xml)->toContain('<enclosure url="'.url('/assets/blog/cover.png').'" type="image/*" />');
    });

    it('excludes unpublished and trashed articles', function (): void {
        feedArticle();
        feedArticle(['is_published' => false, 'published_at' => null]);
        feedArticle(['deleted_at' => now()]);

        $xml = (string) $this->get('/rss')->getContent();

        expect(substr_count($xml, '<item>'))->toBe(1);
    });

    it('localizes the channel and item content by the locale cookie', function (): void {
        feedArticle();

        $xml = (string) $this->withUnencryptedCookie('locale', 'ru')->get('/rss')->getContent();

        expect($xml)->toContain('Платёжный шлюз')
            ->and($xml)->toContain('<title>Блог — </title>');
    });

    it('escapes XML-special characters in titles', function (): void {
        feedArticle([], [
            'en' => ['title' => 'A & B <C>', 'short_description' => 'x & y', 'content' => 'Body with enough words.'],
        ]);

        $xml = (string) $this->get('/rss')->getContent();

        expect($xml)->toContain('<title>A &amp; B &lt;C&gt;</title>');
    });
});
