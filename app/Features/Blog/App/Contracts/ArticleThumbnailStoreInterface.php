<?php

declare(strict_types=1);

namespace App\Features\Blog\App\Contracts;

/**
 * Reads and records the card thumbnail of an article lead image. The
 * guarded store only writes when the row still carries the lead image
 * that was processed, so a replacement racing the job cannot record a
 * thumbnail of the previous image.
 */
interface ArticleThumbnailStoreInterface
{
    /**
     * The stored lead image path — null when the row is gone or has no
     * lead image.
     */
    public function leadImagePathOf(int $articleId): ?string;

    public function store(int $articleId, string $sourcePath, string $thumbnailPath): void;
}
