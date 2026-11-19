<?php

declare(strict_types=1);

namespace App\Features\Projects\App\Contracts;

/**
 * Reads and records the card thumbnail of a project screenshot. The
 * guarded store only writes when the row still carries the source path
 * that was processed, so a replacement racing the job cannot record a
 * thumbnail of the previous image.
 */
interface ScreenshotThumbnailStoreInterface
{
    /**
     * The stored image path of the screenshot — null when the row is
     * gone or has no image.
     */
    public function imagePathOf(int $screenshotId): ?string;

    public function store(int $screenshotId, string $sourcePath, string $thumbnailPath): void;
}
