<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use App\Features\Shared\App\UseCases\GetSitemapXmlUseCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/sitemap.xml` (XML, Redis-cached 24 h) and `/robots.txt` (static text).
 */
final class SitemapController
{
    public function __construct(
        private readonly GetSitemapXmlUseCase $sitemapCase,
    ) {
    }

    public function index(): Response
    {
        return ResponseFacade::make($this->sitemapCase->get(), 200, [
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
