<?php

declare(strict_types=1);

namespace App\Features\Backup\Infra\Storage;

use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;

use function array_keys;
use function config;
use function is_string;

/**
 * The RustFS bucket enumerator: calls `ListObjectsV2` with
 * continuation-token pagination (max 1000 keys per request) and returns
 * every object key of the given bucket — no DB introspection. The bucket
 * name selects the disk whose configured bucket matches (`portfolio` →
 * `rustfs`, `backups` → `rustfs-backups`), so the correct credentials
 * are used per bucket.
 */
final class RustFsAssetPathCollector implements AssetPathCollectorInterface
{
    private const MAX_KEYS_PER_REQUEST = 1000;

    public function __construct(
        private readonly FilesystemManager $filesystem,
    ) {
    }

    public function listPaths(string $bucket): array
    {
        $client = $this->filesystem->disk($this->diskNameFor($bucket))->getClient();

        $paths = [];
        $continuationToken = null;

        do {
            $result = $client->listObjectsV2([
                'Bucket' => $bucket,
                'MaxKeys' => self::MAX_KEYS_PER_REQUEST,
                'ContinuationToken' => $continuationToken,
            ]);

            foreach ($result['Contents'] ?? [] as $object) {
                $key = $object['Key'] ?? null;

                if (is_string($key)) {
                    $paths[] = $key;
                }
            }

            $continuationToken = isset($result['IsTruncated']) && $result['IsTruncated']
                ? ($result['NextContinuationToken'] ?? null)
                : null;
        } while ($continuationToken !== null);

        return $paths;
    }

    private function diskNameFor(string $bucket): string
    {
        foreach (array_keys(config('filesystems.disks')) as $diskName) {
            if ((string) config("filesystems.disks.{$diskName}.bucket") === $bucket) {
                return $diskName;
            }
        }

        throw new InvalidArgumentException("Unknown RustFS bucket '{$bucket}'.");
    }
}
