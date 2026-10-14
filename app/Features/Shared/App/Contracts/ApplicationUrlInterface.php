<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The site's absolute base URL (no trailing slash) — the origin the app-layer
 * SEO data resolves canonical, asset and Open Graph URLs against. An
 * abstraction over the framework's `app.url` so the app layer never reads
 * framework configuration; the framework-backed implementation lives on the
 * infra layer.
 */
interface ApplicationUrlInterface
{
    public function baseUrl(): string;
}
