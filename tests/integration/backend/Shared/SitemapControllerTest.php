<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\Infra\Services\SitemapService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

/**
 * Seeds a published article/project pair plus unpublished + trashed rows
 * that must never appear in the sitemap.
 */
function seedSitemapEntities(): void
{
    DB::table('blog_articles')->insert([
        ['slug' => 'published-article', 'is_published' => true, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ['slug' => 'draft-article', 'is_published' => false, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ['slug' => 'trashed-article', 'is_published' => true, 'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('projects')->insert([
        ['slug' => 'published-project', 'is_published' => true, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ['slug' => 'draft-project', 'is_published' => false, 'deleted_at' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
}

describe('SitemapController', function (): void {
    it('serves the cached sitemap as application/xml', function (): void {
        seedSitemapEntities();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);

        expect(Cache::has('sitemap:xml'))->toBeTrue();
    });

    it('includes the static pages with their priorities and xhtml alternates', function (): void {
        $body = $this->get('/sitemap.xml')->getContent();

        expect($body)
            ->toContain('<loc>'.config('app.url').'/</loc>')
            ->toContain('<loc>'.config('app.url').'/projects</loc>')
            ->toContain('<loc>'.config('app.url').'/blog</loc>')
            ->toContain('<loc>'.config('app.url').'/experience</loc>')
            ->toContain('<priority>1</priority>')
            ->toContain('<priority>0.8</priority>')
            ->toContain('<priority>0.5</priority>')
            ->toContain('<xhtml:link rel="alternate" hreflang="en" href="'.config('app.url').'/en"/>')
            ->toContain('<xhtml:link rel="alternate" hreflang="ru" href="'.config('app.url').'/ru"/>')
            ->toContain('<xhtml:link rel="alternate" hreflang="x-default" href="'.config('app.url').'/"/>');
    });

    it('includes published articles and projects with lastmod, omitting drafts and trashed rows', function (): void {
        seedSitemapEntities();

        $body = $this->get('/sitemap.xml')->getContent();

        expect($body)
            ->toContain('<loc>'.config('app.url').'/blog/published-article</loc>')
            ->toContain('<loc>'.config('app.url').'/projects/published-project</loc>')
            ->toContain('<lastmod>')
            ->not->toContain('draft-article')
            ->not->toContain('trashed-article')
            ->not->toContain('draft-project');
    });

    it('regenerates after invalidation', function (): void {
        seedSitemapEntities();

        $this->get('/sitemap.xml')->assertSee('published-article', false);

        DB::table('blog_articles')->insert([
            'slug' => 'second-article', 'is_published' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // The cache is still warm — the stale copy lacks the new article.
        $this->get('/sitemap.xml')->assertDontSee('second-article', false);

        app(SitemapService::class)->invalidate();

        $this->get('/sitemap.xml')->assertSee('second-article', false);
    });

    it('omits disabled locales from the alternates', function (): void {
        DB::table('settings')->where('key', 'supported_locales')->update(['value' => '["en"]']);

        $body = $this->get('/sitemap.xml')->getContent();

        expect($body)
            ->toContain('hreflang="en"')
            ->not->toContain('hreflang="ru"');
    });

    it('serves robots.txt with the exact seo.md content', function (): void {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertContent(implode("\n", [
                'User-agent: *',
                'Disallow: /admin/',
                'Disallow: /api/',
                'Allow: /',
                'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml',
            ]));
    });
});
