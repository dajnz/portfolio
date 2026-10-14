<?php

declare(strict_types=1);

namespace App\Features\Resume\App\Services;

use App\Features\Resume\App\Contracts\ResumePdfCacheInterface;
use App\Features\Shared\App\Contracts\AssetStorageInterface;

/**
 * The generated resume PDF storage: the cache path and the RustFS object
 * behind one collaborator, so the `/cv` use case only asks for an existing
 * PDF or writes a fresh one.
 */
final class ResumePdfStore
{
    public function __construct(
        private readonly ResumePdfCacheInterface $cache,
        private readonly AssetStorageInterface $storage,
    ) {
    }

    /**
     * The path of an already-generated PDF: the cached path while its file
     * still exists, else the conventional path while that file exists (the
     * cache-cleared-but-file-present case — re-cached on the way). Null when
     * neither exists.
     */
    public function find(string $key, string $path): ?string
    {
        $cached = $this->cache->cachedPath($key);

        if ($cached !== null && $this->storage->exists($cached)) {
            return $cached;
        }

        if ($this->storage->exists($path)) {
            $this->cache->storePath($key, $path);

            return $path;
        }

        return null;
    }

    public function write(string $key, string $path, string $contents): void
    {
        $this->storage->write($path, $contents);
        $this->cache->storePath($key, $path);
    }
}
