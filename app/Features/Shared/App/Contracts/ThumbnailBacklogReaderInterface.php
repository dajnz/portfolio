<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The READ side of the thumbnail backfill: the stored screenshots and
 * lead images that still need a thumbnail pair (`includeGenerated` adds
 * the rows that already have one — the `--force` mode).
 */
interface ThumbnailBacklogReaderInterface
{
    /**
     * @return int[]
     */
    public function screenshotIds(bool $includeGenerated = false): array;

    /**
     * @return int[]
     */
    public function articleIds(bool $includeGenerated = false): array;
}
