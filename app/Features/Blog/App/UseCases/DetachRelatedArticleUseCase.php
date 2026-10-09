<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Unlinks two articles bidirectionally: both pair rows are removed
 * atomically.
 */
final class DetachRelatedArticleUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function detach(int $articleId, int $relatedArticleId): void
    {
        $this->repository->detachRelated($articleId, $relatedArticleId);

        $this->countCache->invalidate('articles');
    }
}
