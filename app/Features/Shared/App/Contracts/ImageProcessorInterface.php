<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * Converts a stored-candidate image into the normalized WebP bytes the
 * asset pipeline stores. The framework-backed implementation lives on the
 * infra layer.
 */
interface ImageProcessorInterface
{
    public function toWebp(string $sourcePath, int $maxDimension): string;

    /**
     * Center-crops the encoded contents to the given box and encodes a
     * metadata-free WebP. Sources smaller than the box are never
     * upscaled — the result keeps the source's width and the box's
     * aspect ratio.
     */
    public function toThumbnail(string $contents, int $width, int $height, int $quality): string;
}
