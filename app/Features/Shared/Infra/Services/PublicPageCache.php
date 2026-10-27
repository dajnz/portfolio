<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Caches assembled public page props under a version token. A flush writes a
 * new token, which invalidates every entry at once and lets the old ones
 * expire by TTL — no per-page bookkeeping. Disabled when
 * `portfolio.public_cache.enabled` is false, so development and tests render
 * straight from the repositories.
 */
final class PublicPageCache
{
    private const VERSION_KEY = 'public_page_cache:version';

    /**
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public function remember(string $key, Closure $callback): array
    {
        if (! $this->enabled()) {
            return $callback();
        }

        return Cache::remember(
            $this->cacheKey($key),
            (int) Config::get('portfolio.public_cache.ttl'),
            $callback,
        );
    }

    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, Str::uuid()->toString());
    }

    private function enabled(): bool
    {
        return (bool) Config::get('portfolio.public_cache.enabled');
    }

    private function cacheKey(string $key): string
    {
        $version = (string) Cache::get(self::VERSION_KEY, 'v1');

        return 'public_page_cache:'.$version.':'.$key;
    }
}
