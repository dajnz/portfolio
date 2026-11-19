<?php

declare(strict_types=1);

namespace App\Features\Projects\App\UseCases;

use App\Features\Projects\App\Contracts\ScreenshotThumbnailStoreInterface;
use App\Features\Shared\App\Contracts\LoggerInterface;
use App\Features\Shared\App\Contracts\PublicCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\ThumbnailGeneratorInterface;

/**
 * Generates the card thumbnail pair of one project screenshot and
 * records the 1x path. A row deleted before the job ran, a missing
 * source object and an undecodable source are skipped — the cards keep
 * the full-size fallback — and the public page cache is flushed once the
 * pair is stored so the cards pick it up before the cache TTL.
 */
final class GenerateScreenshotThumbnailUseCase
{
    public function __construct(
        private readonly ScreenshotThumbnailStoreInterface $screenshots,
        private readonly ThumbnailGeneratorInterface $thumbnails,
        private readonly PublicCacheInvalidatorInterface $publicCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(int $screenshotId): void
    {
        $sourcePath = $this->screenshots->imagePathOf($screenshotId);

        if ($sourcePath === null) {
            return;
        }

        $thumbnail = $this->thumbnails->thumbnailFor($sourcePath);

        if ($thumbnail === null) {
            $this->logger->warning('Screenshot thumbnail skipped: the source is missing or not decodable.', [
                'screenshot_id' => $screenshotId,
                'path' => $sourcePath,
            ]);

            return;
        }

        $this->screenshots->store($screenshotId, $sourcePath, $thumbnail->path);
        $this->publicCache->invalidate();
    }
}
