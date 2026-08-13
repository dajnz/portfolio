<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Links two articles bidirectionally (content-management.md:252): the
 * repository inserts both pair rows atomically; a duplicate link
 * surfaces as a 409 via the domain exception.
 */
final class AttachRelatedArticleUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function attach(int $articleId, int $relatedArticleId): void
    {
        $this->repository->attachRelated($articleId, $relatedArticleId);

        $this->countCache->invalidate('articles');
    }
}
