<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Shared\App\Contracts\SitemapXmlProviderInterface;

final class GetSitemapXmlUseCase
{
    public function __construct(
        private readonly SitemapXmlProviderInterface $sitemap,
    ) {
    }

    public function get(): string
    {
        return $this->sitemap->xml();
    }
}
