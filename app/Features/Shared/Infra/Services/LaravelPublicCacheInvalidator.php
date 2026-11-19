<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\PublicCacheInvalidatorInterface;

final class LaravelPublicCacheInvalidator implements PublicCacheInvalidatorInterface
{
    public function __construct(
        private readonly PublicPageCache $cache,
    ) {
    }

    public function invalidate(): void
    {
        $this->cache->flush();
    }
}
