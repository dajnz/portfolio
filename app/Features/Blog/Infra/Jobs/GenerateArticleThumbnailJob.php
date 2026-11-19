<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Jobs;

use App\Features\Blog\App\UseCases\GenerateArticleThumbnailUseCase;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The queued card-thumbnail generation of one article lead image. The
 * skip policy, the guarded row update and the cache flush live in
 * {@see GenerateArticleThumbnailUseCase}, not here.
 */
final class GenerateArticleThumbnailJob implements ShouldBeUnique, ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $articleId,
    ) {
    }

    public function uniqueId(): string
    {
        return 'article-thumbnail:'.$this->articleId;
    }

    public function handle(GenerateArticleThumbnailUseCase $thumbnails): void
    {
        $thumbnails->generate($this->articleId);
    }
}
