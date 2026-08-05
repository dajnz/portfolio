<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use App\Features\Shared\Infra\Services\SitemapService;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/sitemap.xml` (XML, Redis-cached 24 h) and `/robots.txt` (static text)
 * — seo.md:194-231.
 */
final class SitemapController
{
    public function index(SitemapService $sitemap): Response
    {
        return response($sitemap->getXml(), 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function robots(): Response
    {
        $siteUrl = rtrim((string) config('app.url'), '/');

        $content = implode("\n", [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Allow: /',
            'Sitemap: '.$siteUrl.'/sitemap.xml',
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
