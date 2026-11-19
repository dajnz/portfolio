<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

use App\Features\Shared\App\DTOs\ThumbnailDto;

/**
 * Stores the 1x/2x card thumbnail pair of an already stored object. Null
 * means the generation is not possible — the source object is missing or
 * its bytes are not a decodable image — and the cards keep the full-size
 * fallback (ADR-0047).
 */
interface ThumbnailGeneratorInterface
{
    public function thumbnailFor(string $sourcePath): ?ThumbnailDto;
}
