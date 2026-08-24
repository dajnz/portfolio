<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Backup\App\Contracts\AssetPathCollectorInterface;
use Illuminate\Filesystem\FilesystemManager;

use function config;
use function is_string;

/**
 * Disk-backed `AssetPathCollectorInterface` double for the integration
 * suite: enumerates the bucket's disk contents directly (the real
 * collector talks to the S3 client, which `Storage::fake` cannot
 * serve). The disk is resolved by the configured bucket name — the
 * same mapping the real collector uses — so `Storage::fake('rustfs')`
 * contents are what `listPaths('portfolio')` returns.
 */
final class FakeRustFsAssetCollector implements AssetPathCollectorInterface
{
    public function __construct(
        private readonly FilesystemManager $filesystem,
    ) {
    }

    public function listPaths(string $bucket): array
    {
        $diskName = $this->diskNameFor($bucket);

        if ($diskName === null) {
            return [];
        }

        return $this->filesystem->disk($diskName)->allFiles();
    }

    private function diskNameFor(string $bucket): ?string
    {
        foreach (array_keys(config('filesystems.disks')) as $diskName) {
            if ((string) config("filesystems.disks.{$diskName}.bucket") === $bucket && is_string($diskName)) {
                return $diskName;
            }
        }

        return null;
    }
}
