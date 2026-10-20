<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Produces the current `/sitemap.xml` document. The cached, database-backed
 * implementation lives on the infra layer.
 */
interface SitemapXmlProviderInterface
{
    public function xml(): string;
}
