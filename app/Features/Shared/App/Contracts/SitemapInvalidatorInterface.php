<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Invalidates the `sitemap:xml` cache after a content mutation that affects
 * the sitemap (a published article/project save or delete, a publication
 * toggle). An abstraction over
 * {@see \App\Features\Shared\Infra\Services\SitemapService} so the app layer
 * never touches the infra service directly; the framework-backed
 * implementation lives on the infra layer. The invalidation decision lives
 * in the app-layer use cases — neither the Eloquent models nor the
 * repositories orchestrate it.
 */
interface SitemapInvalidatorInterface
{
    public function invalidate(): void;
}
