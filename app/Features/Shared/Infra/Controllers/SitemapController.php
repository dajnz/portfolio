<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use App\Features\Shared\Infra\Services\SitemapService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/sitemap.xml` (XML, Redis-cached 24 h) and `/robots.txt` (static text).
 */
final class SitemapController
{
    public function index(SitemapService $sitemap): Response
    {
        return ResponseFacade::make($sitemap->getXml(), 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    public function robots(): Response
    {
        $siteUrl = rtrim((string) Config::get('app.url'), '/');

        $content = implode("\n", [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Allow: /',
            'Sitemap: '.$siteUrl.'/sitemap.xml',
        ]);

        return ResponseFacade::make($content, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
