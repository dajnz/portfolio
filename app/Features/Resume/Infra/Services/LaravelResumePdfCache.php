<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Services;

use App\Features\Resume\App\Contracts\ResumePdfCacheInterface;
use Illuminate\Support\Facades\Cache;

/**
 * The framework cache store (the `redis` store) behind the app-layer
 * {@see ResumePdfCacheInterface} contract.
 */
final class LaravelResumePdfCache implements ResumePdfCacheInterface
{
    public function cachedPath(string $key): ?string
    {
        $cached = Cache::store('redis')->get($key);

        return is_string($cached) ? $cached : null;
    }

    public function storePath(string $key, string $path): void
    {
        Cache::store('redis')->put($key, $path);
    }
}
