<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\AssetCleanerInterface;
use Illuminate\Support\Facades\Storage;

/**
 * The {@see AssetCleanerInterface} over the RustFS disk — deletes the
 * given stored object paths in one batch call.
 */
final class RustFsAssetCleaner implements AssetCleanerInterface
{
    public function delete(array $paths): void
    {
        Storage::disk('rustfs')->delete(array_values(array_unique(array_filter($paths))));
    }
}
