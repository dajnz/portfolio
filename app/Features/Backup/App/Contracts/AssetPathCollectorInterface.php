<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * Enumerates every object of a RustFS bucket (backup.md:195-199,
 * 338-350): `ListObjectsV2` with continuation-token pagination
 * (max 1000 keys per request), no DB introspection. Implemented by
 * `RustFsAssetPathCollector` in the Infra layer — used by the backup
 * workflow to stage the `assets/` content and by the restore workflow
 * to mirror the live bucket against the manifest.
 */
interface AssetPathCollectorInterface
{
    /**
     * @return string[] The bucket's object keys.
     */
    public function listPaths(string $bucket): array;
}
