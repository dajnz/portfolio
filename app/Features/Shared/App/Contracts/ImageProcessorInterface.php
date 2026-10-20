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
}
