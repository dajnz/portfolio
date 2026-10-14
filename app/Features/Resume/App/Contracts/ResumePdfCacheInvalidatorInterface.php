<?php

declare(strict_types=1);

namespace App\Features\Resume\App\Contracts;

/**
 * Clears every cached resume PDF path — the invalidation primitive used when
 * the source data changes. An abstraction over the framework's cache store so
 * the app layer never names it; the implementation lives on the infra layer.
 */
interface ResumePdfCacheInvalidatorInterface
{
    public function clear(): void;
}
