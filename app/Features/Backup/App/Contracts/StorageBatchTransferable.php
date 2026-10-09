<?php

declare(strict_types=1);

namespace App\Features\Backup\App\Contracts;

/**
 * Batch object transfer to/from the archive storage: concurrent
 * downloads/uploads keyed by path, plus batch deletion (the restore's
 * delete-extras mirror and the archive hard-deletes of delete/prune).
 * The contract is storage-agnostic — the Infra layer's
 * `RustFsBatchTransfer` implements it over RustFS; the app layer never
 * names the storage backend. Deleting a missing object is a no-op.
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

    /**
     * Hard-deletes the given object keys (the restore's exact mirror —
     * objects absent from the manifest — and the archive deletions of
     * `DestroyBackupUseCase`/`PruneBackupsUseCase`).
     *
     * @param  list<string>  $remotePaths
     */
    public function deleteObjects(array $remotePaths): void;
}
