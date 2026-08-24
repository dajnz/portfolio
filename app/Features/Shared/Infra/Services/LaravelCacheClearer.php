<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\CacheClearerInterface;
use Illuminate\Support\Facades\Cache;

/**
 * The framework-backed cache flush (backup.md:406) behind the
 * `CacheClearerInterface` — the restore's refresh step.
 */
final class LaravelCacheClearer implements CacheClearerInterface
{
    public function flush(): void
    {
        Cache::flush();
    }
}
