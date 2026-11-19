<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\App\Contracts\ArticleThumbnailStoreInterface;
use App\Features\Shared\App\Contracts\LoggerInterface;
use App\Features\Shared\App\Contracts\PublicCacheInvalidatorInterface;
use App\Features\Shared\App\Contracts\ThumbnailGeneratorInterface;

/**
 * Generates the card thumbnail pair of one article lead image and records
 * the 1x path. A row deleted before the job ran, a missing lead image, a
 * missing source object and an undecodable source are skipped — the cards
 * keep the full-size fallback — and the public page cache is flushed once
 * the pair is stored so the cards pick it up before the cache TTL.
 */
final class GenerateArticleThumbnailUseCase
{
    public function __construct(
        private readonly ArticleThumbnailStoreInterface $articles,
        private readonly ThumbnailGeneratorInterface $thumbnails,
        private readonly PublicCacheInvalidatorInterface $publicCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generate(int $articleId): void
    {
        $sourcePath = $this->articles->leadImagePathOf($articleId);

        if ($sourcePath === null) {
            return;
        }

        $thumbnail = $this->thumbnails->thumbnailFor($sourcePath);

        if ($thumbnail === null) {
            $this->logger->warning('Article thumbnail skipped: the source is missing or not decodable.', [
                'article_id' => $articleId,
                'path' => $sourcePath,
            ]);

            return;
        }

        $this->articles->store($articleId, $sourcePath, $thumbnail->path);
        $this->publicCache->invalidate();
    }
}
