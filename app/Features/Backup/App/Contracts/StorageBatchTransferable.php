<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * Batch object transfer to/from the archive storage (backup.md:201-207,
 * 353-369): concurrent downloads/uploads keyed by path. The contract
 * is storage-agnostic — the Infra layer's `RustFsBatchTransfer`
 * implements it over RustFS; the app layer never names the storage
 * backend.
 */
interface StorageBatchTransferable
{
    /**
     * Downloads the given objects into the local paths (asset staging
     * before zipping; the archive download for restore).
     *
     * @param  array<string, string>  $remoteToLocal  object key => local path
     */
    public function downloadBatch(array $remoteToLocal): void;

    /**
     * Uploads the given local files to their object keys (the
     * created archive; the restored assets mirror).
     *
     * @param  array<string, string>  $localToRemote  local path => object key
     */
    public function uploadBatch(array $localToRemote): void;
}
