<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Services;

use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;

/**
 * Clears every `resume:pdf:*` Redis key — the invalidation primitive for
 * the resume PDF cache. The keys always live in the dedicated `redis`
 * store: the phpredis client prefixes raw commands with the connection
 * prefix, so the pattern scan must carry the cache-store prefix, and the
 * matched names must be stripped back to the store-level key before
 * deletion (the client re-applies its prefix to `del` arguments).
 */
final class ResumePdfCacheInvalidator
{
    public function clear(): void
    {
        $store = Cache::store('redis')->getStore();

        if (! $store instanceof RedisStore) {
            return;
        }

        $client = $store->connection();
        $connectionPrefix = (string) $client->getOption(\Redis::OPT_PREFIX);
        $cachePrefix = $store->getPrefix();

        $keys = array_map(
            static fn (string $key): string => str_starts_with($key, $connectionPrefix)
                ? substr($key, strlen($connectionPrefix))
                : $key,
            $client->keys($cachePrefix.'resume:pdf:*'),
        );

        if ($keys !== []) {
            $client->del($keys);
        }
    }
}
