<?php

declare(strict_types=1);

namespace App\Features\Resume\App\Contracts;

/**
 * The resume PDF path cache: the generated PDF's RustFS path keyed by the
 * source-content hash, so a repeated `/cv` request serves the stored file
 * without re-rendering. An abstraction over the framework's cache store so the
 * app layer never names it; the framework-backed implementation lives on the
 * infra layer.
 */
interface ResumePdfCacheInterface
{
    public function cachedPath(string $key): ?string;

    public function storePath(string $key, string $path): void;
}
