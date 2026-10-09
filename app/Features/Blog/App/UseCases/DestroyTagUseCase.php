<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleTagRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Hard-deletes an article tag (no trash for tags; the
 * `article_tag_pivot` rows cascade via the FK).
 */
final class DestroyTagUseCase
{
    public function __construct(
        private readonly ArticleTagRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function destroy(int $id): void
    {
        $this->repository->delete($id);

        $this->countCache->invalidate('tags');
    }
}
