<?php

declare(strict_types=1);

namespace App\Features\Blog\App\UseCases;

use App\Features\Blog\Domain\RepositoryInterfaces\ArticleRepositoryInterface;
use App\Features\Shared\App\Contracts\CountCacheInvalidatorInterface;

/**
 * Attaches a tech skill tag via `article_skill_pivot`
 * (content-management.md:253) — a duplicate attachment surfaces as a
 * 409 via the domain exception.
 */
final class AttachArticleSkillUseCase
{
    public function __construct(
        private readonly ArticleRepositoryInterface $repository,
        private readonly CountCacheInvalidatorInterface $countCache,
    ) {
    }

    public function attach(int $articleId, int $skillId): void
    {
        $this->repository->attachSkill($articleId, $skillId);

        $this->countCache->invalidate('articles');
    }
}
