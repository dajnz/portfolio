<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\SitemapInvalidatorInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Builds and caches `/sitemap.xml`. The XML string is cached in Redis under
 * `sitemap:xml` with a 24-hour TTL; the cache is invalidated by the feature
 * WRITE USE CASES via the shared {@see SitemapInvalidatorInterface} (the
 * invalidation decisions live in the app layer — neither the Eloquent models
 * nor the repositories orchestrate them). Only published, non-trashed entities
 * are included.
 *
 * lastmod: `base.updated_at`. The translation tables carry no timestamps, so
 * lastmod reflects base-table edits only.
 *
 * hreflang: each entry lists the locale-prefixed alternates for every enabled
 * locale plus the `x-default` canonical URL. Disabled locales are omitted.
 */
final class SitemapService implements SitemapInvalidatorInterface
{
    private const CACHE_KEY = 'sitemap:xml';

    private const CACHE_TTL_SECONDS = 86400;

    private const URLSET_XMLNS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    private const XHTML_XMLNS = 'http://www.w3.org/1999/xhtml';

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    public function getXml(): string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): string => $this->generate());
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function generate(): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $locales = $this->enabledLocales();

        $urls = [
            ['loc' => $baseUrl.'/', 'priority' => 1.0],
            ['loc' => $baseUrl.'/projects', 'priority' => 0.8],
            ['loc' => $baseUrl.'/blog', 'priority' => 0.8],
            ['loc' => $baseUrl.'/experience', 'priority' => 0.5],
        ];

        foreach ($this->publishedRows('blog_articles') as $row) {
            $urls[] = ['loc' => $baseUrl.'/blog/'.$row->slug, 'lastmod' => $row->lastmod, 'priority' => 0.6];
        }

        foreach ($this->publishedRows('projects') as $row) {
            $urls[] = ['loc' => $baseUrl.'/projects/'.$row->slug, 'lastmod' => $row->lastmod, 'priority' => 0.7];
        }

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = sprintf('<urlset xmlns="%s" xmlns:xhtml="%s">', self::URLSET_XMLNS, self::XHTML_XMLNS);

        foreach ($urls as $url) {
            $lines[] = $this->urlBlock($url, $baseUrl, $locales);
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }

    /**
     * @return string[]
     */
    private function enabledLocales(): array
    {
        $locales = $this->settings->value(SettingName::SupportedLocales, config('portfolio.supported_locales', ['en', 'ru']));

        return is_array($locales) ? array_values(array_filter($locales, 'is_string')) : ['en'];
    }

    /**
     * @return stdClass[] rows with `slug` + `lastmod` (formatted)
     */
    private function publishedRows(string $table): array
    {
        return DB::table($table)
            ->selectRaw('slug, updated_at as lastmod')
            ->where('is_published', true)
            ->whereNull('deleted_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (stdClass $row): stdClass {
                $row->lastmod = date('c', strtotime((string) $row->lastmod));

                return $row;
            })
            ->all();
    }

    /**
     * @param  array{loc: string, lastmod?: string, priority: float}  $url
     * @param  string[]  $locales
     */
    private function urlBlock(array $url, string $baseUrl, array $locales): string
    {
        $path = parse_url($url['loc'], PHP_URL_PATH) ?: '/';

        $lines = ['  <url>', "    <loc>{$url['loc']}</loc>"];

        if (isset($url['lastmod'])) {
            $lines[] = "    <lastmod>{$url['lastmod']}</lastmod>";
        }

        $lines[] = sprintf('    <priority>%s</priority>', rtrim(rtrim((string) $url['priority'], '0'), '.'));

        foreach ($locales as $locale) {
            $alternate = $path === '/' ? $baseUrl.'/'.$locale : $baseUrl.'/'.$locale.$path;
            $lines[] = sprintf('    <xhtml:link rel="alternate" hreflang="%s" href="%s"/>', $locale, $alternate);
        }

        $lines[] = sprintf('    <xhtml:link rel="alternate" hreflang="x-default" href="%s"/>', $url['loc']);

        $lines[] = '  </url>';

        return implode("\n", $lines);
    }
}
