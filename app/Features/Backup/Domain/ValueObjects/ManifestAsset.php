<?php

declare(strict_types=1);

namespace App\Features\Backup\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * One entry of the manifest's `assets` array (backup.md:119-128): a
 * RustFS object path of the portfolio bucket and its byte size. The
 * paths enumerate every asset included in the archive — the restore
 * workflow mirrors the live bucket against them, deleting objects
 * absent from the manifest.
 */
final class ManifestAsset
{
    public function __construct(
        private readonly string $path,
        private readonly int $sizeBytes,
    ) {
        if ($path === '') {
            throw new InvalidArgumentException('Manifest asset path must not be empty.');
        }

        if ($sizeBytes < 0) {
            throw new InvalidArgumentException('Manifest asset size must not be negative.');
        }
    }

    public function toPath(): string
    {
        return $this->path;
    }

    public function toSizeBytes(): int
    {
        return $this->sizeBytes;
    }
}
