<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Detaches a tech skill tag via `article_skill_pivot`
 * (content-management.md:253).
 */
final class DetachArticleSkillUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function detach(int $articleId, int $skillId): void
    {
        $this->repository->detachSkill($articleId, $skillId);

        $this->countCache->invalidate('articles');
    }
}
