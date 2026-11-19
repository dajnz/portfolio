<?php

declare(strict_types=1);

namespace App\Features\Shared\App\DTOs;

/**
 * The number of queued/generated thumbnails of the maintenance command —
 * one count per image source. Immutable.
 */
final readonly class ThumbnailBacklogDto
{
    public function __construct(
        public int $screenshots,
        public int $articles,
    ) {
    }
}
