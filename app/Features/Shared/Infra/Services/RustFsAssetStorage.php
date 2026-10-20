<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\AssetStorageInterface;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The immutable RustFS assets disk behind the app-layer
 * {@see AssetStorageInterface} contract.
 */
final class RustFsAssetStorage implements AssetStorageInterface
{
    public function exists(string $path): bool
    {
        return Storage::disk('rustfs')->exists($path);
    }

    public function write(string $path, string $contents): void
    {
        Storage::disk('rustfs')->put($path, $contents);
    }

    public function writeFile(string $path, string $sourcePath): void
    {
        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("Unable to read the source file `{$sourcePath}`.");
        }

        try {
            Storage::disk('rustfs')->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function size(string $path): int
    {
        return Storage::disk('rustfs')->size($path);
    }

    public function files(string $directory): array
    {
        return Storage::disk('rustfs')->files($directory);
    }

    public function delete(array $paths): void
    {
        Storage::disk('rustfs')->delete(array_values(array_unique(array_filter($paths))));
    }
}
