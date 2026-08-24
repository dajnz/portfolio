<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Storage;

use App\Features\Backup\App\Contracts\StorageBatchTransferable;
use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

use function fclose;
use function fopen;
use function is_resource;
use function stream_copy_to_stream;

/**
 * The batch object transfer over one RustFS disk (backup.md:353-372):
 * `downloadBatch()` pulls the given object keys into their local paths
 * (asset staging before zipping; the archive download for restore) and
 * `uploadBatch()` pushes the given local files to their object keys
 * (the created archive; the restored assets mirror) — each transfer
 * streamed, so memory stays bounded on large objects. The disk is
 * injected per bucket (the assets disk for the portfolio bucket, the
 * backups disk for the `backups` bucket — backup.md:474-475).
 */
final class RustFsBatchTransfer implements StorageBatchTransferable
{
    public function __construct(
        private readonly Filesystem $disk,
    ) {
    }

    public function downloadBatch(array $remoteToLocal): void
    {
        foreach ($remoteToLocal as $remotePath => $localPath) {
            $stream = $this->disk->readStream($remotePath);

            if ($stream === false) {
                throw new RuntimeException("Unable to read the RustFS object '{$remotePath}'.");
            }

            $target = fopen($localPath, 'wb');

            if ($target === false) {
                throw new RuntimeException("Unable to open the local path '{$localPath}' for writing.");
            }

            try {
                stream_copy_to_stream($stream, $target);
            } finally {
                fclose($target);
                fclose($stream);
            }
        }
    }

    public function uploadBatch(array $localToRemote): void
    {
        foreach ($localToRemote as $localPath => $remotePath) {
            $stream = fopen($localPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException("Unable to open the local path '{$localPath}' for reading.");
            }

            try {
                $this->disk->writeStream($remotePath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }
}
